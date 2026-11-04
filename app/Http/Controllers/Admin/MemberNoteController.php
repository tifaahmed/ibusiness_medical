<?php

namespace App\Http\Controllers\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\MemberNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff notes about members. Everyone allowed to view notes reads all of them; only the author may
 * change or delete a note — enforced here (even the super admin cannot edit somebody else's note).
 */
class MemberNoteController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(UserPermissionEnum::VIEW_MEMBER_NOTES), 403);

        $search = trim((string) $request->input('search', ''));
        $authorId = $request->filled('author_id') ? (int) $request->input('author_id') : null;

        $page = MemberNote::query()
            ->with(['author:id,name', 'member:id,name,phone,slug', 'member.memberships' => fn ($m) => $m->select(['id', 'user_id', 'membership_number'])->latest('id')])
            ->when($authorId, fn ($q, $id) => $q->where('created_by', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('body', 'like', "%{$search}%")
                ->orWhereHas('member', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('memberships', fn ($m) => $m->where('membership_number', 'like', "%{$search}%")))))
            ->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('Admin/MemberNotes/Index', [
            'rows' => collect($page->items())->map(fn (MemberNote $n) => $this->present($n, $request->user()))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'links' => $page->linkCollection()->toArray()],
            'filters' => ['search' => $search, 'author_id' => $authorId],
            'authors' => User::query()->whereIn('id', MemberNote::query()->whereNotNull('created_by')->select('created_by'))->orderBy('name')->get(['id', 'name'])->all(),
            'canAdd' => $request->user()->can(UserPermissionEnum::ADD_MEMBER_NOTES),
        ]);
    }

    /** Find a member to write a note about: name, phone or card number. */
    public function members(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::ADD_MEMBER_NOTES) || $request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(User::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")
                ->orWhereHas('memberships', fn ($m) => $m->where('membership_number', 'like', "%{$q}%")))
            ->whereHas('memberships')
            ->with(['memberships' => fn ($m) => $m->select(['id', 'user_id', 'membership_number'])->latest('id')])
            ->limit(15)->get(['id', 'name', 'phone'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'membership_number' => $u->memberships->first()?->membership_number])
            ->all());
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::ADD_MEMBER_NOTES), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        MemberNote::create(['user_id' => $data['user_id'], 'created_by' => $request->user()->id, 'body' => trim($data['body'])]);

        return back()->with('success', 'Note added.');
    }

    public function update(Request $request, MemberNote $note): RedirectResponse
    {
        abort_unless((int) $note->created_by === (int) $request->user()->id, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $note->update(['body' => trim($data['body'])]);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(Request $request, MemberNote $note): RedirectResponse
    {
        abort_unless((int) $note->created_by === (int) $request->user()->id, 403);
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /** @return array<string, mixed> */
    public static function present(MemberNote $n, User $viewer): array
    {
        return [
            'id' => $n->id,
            'body' => $n->body,
            'author' => $n->author?->name ?? '—',
            'author_id' => $n->created_by,
            'mine' => (int) $n->created_by === (int) $viewer->id,
            'created_at' => $n->created_at?->format('Y-m-d H:i'),
            'edited' => $n->updated_at && $n->created_at && $n->updated_at->gt($n->created_at),
            'member' => $n->relationLoaded('member') && $n->member ? [
                'id' => $n->member->id, 'name' => $n->member->name, 'phone' => $n->member->phone, 'slug' => $n->member->slug,
                'membership_number' => $n->member->memberships->first()?->membership_number,
            ] : null,
        ];
    }
}
