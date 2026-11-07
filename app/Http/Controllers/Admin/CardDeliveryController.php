<?php

namespace App\Http\Controllers\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\CardDelivery;
use App\Models\CardDeliveryNote;
use App\Models\DeliveryCourier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every hand-over of cards to a member: who delivered (courier), when, how many, photos of the cards and notes.
 * A member can have many. Anyone with `manage card deliveries` may edit any of them (the notes keep their writer's name).
 */
class CardDeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(UserPermissionEnum::VIEW_CARD_DELIVERIES), 403);

        $f = [
            'search' => trim((string) $request->input('search', '')),
            'courier_id' => $request->filled('courier_id') ? (int) $request->input('courier_id') : null,
            'status' => in_array($request->input('status'), CardDelivery::STATUSES, true) ? $request->input('status') : '',
        ];

        $page = CardDelivery::query()
            ->with(['courier:id,name,phone', 'creator:id,name', 'noteLines.author:id,name', 'media', 'member:id,name,phone,slug', 'member.memberships' => fn ($m) => $m->select(['id', 'user_id', 'membership_number'])->latest('id')])
            ->when($f['courier_id'], fn ($q, $id) => $q->where('courier_id', $id))
            ->when($f['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('notes', 'like', "%{$f['search']}%")->orWhere('card_numbers', 'like', "%{$f['search']}%")
                ->orWhereHas('member', fn ($u) => $u->where('name', 'like', "%{$f['search']}%")->orWhere('phone', 'like', "%{$f['search']}%")
                    ->orWhereHas('memberships', fn ($m) => $m->where('membership_number', 'like', "%{$f['search']}%")))))
            ->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('Admin/CardDeliveries/Index', [
            'rows' => collect($page->items())->map(fn (CardDelivery $d) => self::present($d, true))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'links' => $page->linkCollection()->toArray()],
            'filters' => $f,
            'couriers' => DeliveryCourier::query()->orderBy('name')->get(['id', 'name', 'is_active'])->all(),
            'canManage' => $request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $data = $request->validate($this->rules() + ['user_id' => ['required', 'integer', 'exists:users,id']]);
        $photos = $request->file('photos', []);

        $delivery = CardDelivery::create($this->clean($data) + ['user_id' => $data['user_id'], 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->attach($delivery, $photos);

        return back()->with('success', 'Delivery recorded.');
    }

    public function update(Request $request, CardDelivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $data = $request->validate($this->rules());
        $delivery->update($this->clean($data) + ['updated_by' => $request->user()->id]);
        $this->attach($delivery, $request->file('photos', []));

        return back()->with('success', 'Delivery updated.');
    }

    public function destroy(Request $request, CardDelivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $delivery->delete();

        return back()->with('success', 'Delivery deleted.');
    }

    public function removePhoto(Request $request, CardDelivery $delivery, int $media): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $delivery->getMedia(CardDelivery::PHOTOS)->firstWhere('id', $media)?->delete();

        return back()->with('success', 'Photo removed.');
    }

    public function addNote(Request $request, CardDelivery $delivery): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $delivery->noteLines()->create(['body' => trim($data['body']), 'created_by' => $request->user()->id]);

        return back()->with('success', 'Note added.');
    }

    public function updateNote(Request $request, CardDeliveryNote $note): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $note->update(['body' => trim($data['body'])]);

        return back()->with('success', 'Note updated.');
    }

    public function destroyNote(Request $request, CardDeliveryNote $note): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'courier_id' => ['nullable', 'integer', 'exists:delivery_couriers,id'],
            'status' => ['required', Rule::in(CardDelivery::STATUSES)],
            'cards_count' => ['required', 'integer', 'min:1', 'max:100'],
            'card_numbers' => ['nullable', 'string', 'max:255'],
            'delivered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'photos' => ['sometimes', 'array', 'max:20'],
            'photos.*' => ['image', 'max:8192'],
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function clean(array $data): array
    {
        return collect($data)->only(['courier_id', 'status', 'cards_count', 'card_numbers', 'delivered_at', 'notes'])->all();
    }

    /** @param array<int, \Illuminate\Http\UploadedFile>|\Illuminate\Http\UploadedFile|null $files */
    private function attach(CardDelivery $delivery, $files): void
    {
        foreach ((array) $files as $file) {
            $delivery->addMedia($file)->toMediaCollection(CardDelivery::PHOTOS);
        }
    }

    /** @return array<string, mixed> */
    public static function present(CardDelivery $d, bool $withMember = false): array
    {
        return [
            'id' => $d->id,
            'status' => $d->status,
            'courier_id' => $d->courier_id,
            'courier' => $d->courier ? ['id' => $d->courier->id, 'name' => $d->courier->name, 'phone' => $d->courier->phone] : null,
            'cards_count' => $d->cards_count,
            'card_numbers' => $d->card_numbers,
            'delivered_at' => $d->delivered_at?->format('Y-m-d'),
            'notes' => $d->notes,
            'recorded_by' => $d->creator?->name,
            'created_at' => $d->created_at?->format('Y-m-d H:i'),
            'photos' => $d->getMedia(CardDelivery::PHOTOS)->map(fn ($m) => ['id' => $m->id, 'url' => $m->getUrl()])->values()->all(),
            'note_lines' => $d->noteLines->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'author' => $n->author?->name ?? '—', 'at' => $n->created_at?->format('Y-m-d H:i')])->all(),
            'member' => $withMember && $d->member ? [
                'id' => $d->member->id, 'name' => $d->member->name, 'phone' => $d->member->phone, 'slug' => $d->member->slug,
                'membership_number' => $d->member->memberships->first()?->membership_number,
            ] : null,
        ];
    }
}
