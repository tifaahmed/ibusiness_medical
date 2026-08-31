<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Contact\UpdateContactMessageAction;
use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\Sales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The enquiry inbox: this site's own contact form, and the three public forms
 * on the Deilar storefront (its contact page, its card popup, and facilities
 * applying to join the network).
 *
 * Every change an admin makes is recorded against the enquiry — see
 * `UpdateContactMessageAction` — so an enquiry that went quiet can be read
 * back to who had it and when.
 */
class ContactMessageController extends Controller
{
    public function __construct(private readonly UpdateContactMessageAction $updateContactMessage) {}

    /**
     * Whether this admin may actually change an enquiry.
     *
     * The read side admits the viewer role's `view contact messages`, so the
     * pages have to know the difference — a Save button that can only ever
     * answer 403 is worse than no Save button.
     */
    private function canManage(Request $request): bool
    {
        return $request->user()?->hasAnyPermission([
            UserPermissionEnum::MANAGE_CONTACT_MESSAGES,
            UserPermissionEnum::MANAGE_MEMBERSHIPS,
        ]) ?? false;
    }

    public function index(Request $request): Response
    {
        $query = ContactMessage::query()->with('sales');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->status($request->string('status')->toString());
        }

        /* Which form it came through. Sales work a join request and a card
           popup very differently, so this is a first-class filter rather than
           something to search for. */
        if ($request->filled('source') && $request->source !== 'all') {
            $query->source($request->string('source')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('commercial_register', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        /* Allow-listed rather than taken from the query string: an arbitrary
           column here is an ORDER BY an attacker chooses. */
        $sortField = in_array($request->get('sort'), ['created_at', 'status', 'source'], true)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $messages = $query->orderBy($sortField, $sortDirection)->paginate(15)->withQueryString();

        $counts = ContactMessage::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/ContactMessages/Index', [
            'messages' => ContactMessageResource::collection($messages),
            'stats' => [
                'total' => (int) $counts->sum(),
                ...collect(ContactStatusEnum::values())
                    ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
                    ->all(),
            ],
            'statuses' => array_values(ContactStatusEnum::getOptions()),
            'sources' => array_values(ContactSourceEnum::getOptions()),
            'canManage' => $this->canManage($request),
            'filters' => [
                'status' => $request->get('status', 'all'),
                'source' => $request->get('source', 'all'),
                'search' => $request->get('search', ''),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    public function show(Request $request, ContactMessage $contactMessage): Response
    {
        /* Stamps "first opened" only. It no longer moves the status: an
           enquiry somebody glanced at is still new work until it is picked up. */
        $contactMessage->markAsRead();

        $contactMessage->load(['sales', 'logs.admin']);

        return Inertia::render('Admin/ContactMessages/Show', [
            'message' => new ContactMessageResource($contactMessage),
            'statuses' => array_values(ContactStatusEnum::getOptions()),
            'salesOptions' => Sales::query()
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Sales $sales) => ['value' => $sales->id, 'label' => $sales->name]),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(ContactStatusEnum::values())],
            'sales_id' => ['sometimes', 'nullable', 'exists:sales,id'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $this->updateContactMessage->handle($contactMessage, $validated, $request->user());

        return redirect()->back()->with('success', 'Contact message updated successfully.');
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()->route('admin.contact-messages.index')
            ->with('success', 'Contact message deleted successfully.');
    }

    /**
     * Move or delete several at once.
     *
     * Each one goes through the same action a single edit does, so a bulk
     * change leaves the same trail behind it as fifty individual ones.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:contact_messages,id'],
            'action' => ['required', 'string', Rule::in([...ContactStatusEnum::values(), 'delete'])],
        ]);

        $messages = ContactMessage::query()->whereIn('id', $validated['ids'])->get();

        if ($validated['action'] === 'delete') {
            ContactMessage::query()->whereIn('id', $validated['ids'])->delete();

            return response()->json([
                'success' => true,
                'message' => 'Messages deleted successfully.',
            ]);
        }

        foreach ($messages as $message) {
            $this->updateContactMessage->handle(
                $message,
                ['status' => $validated['action']],
                $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Messages updated successfully.',
        ]);
    }
}
