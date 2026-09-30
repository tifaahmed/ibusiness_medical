<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in member's own family, and the one thing they may add to it: a
 * child.
 *
 * Token-gated like the orders — `$request->user()` is the whole authorisation
 * story and nothing takes a membership id out of the request. The membership
 * is the member's LATEST one, active or not, for the same reason the profile
 * reads it: a member who registered themselves holds a pending card until
 * staff issue a real one, and must still be able to see and fill in their own.
 *
 * Only a FATHER adds children this way, and only the child's FIRST name is
 * accepted: the rest of the name is the father's own, appended here. It is
 * built on this side rather than trusted from the request, so a client cannot
 * post a child under a different surname. Wives, parents and siblings are
 * still staff's to add — and to change: a father edits or removes only his own
 * sons and daughters, found through HIS membership, so another member's child
 * is a 404 rather than a permission error that would confirm it exists.
 */
class FamilyController extends Controller
{
    /**
     * The most children a member can add for themselves. A ceiling rather than
     * a rule about families: it stops a loop from filling a card.
     */
    private const MAX_CHILDREN = 12;

    /** @var list<string> */
    private const CHILD_RELATIONSHIPS = ['son', 'daughter'];

    /** Letters (any script), marks, spaces, apostrophes and hyphens — no digits. */
    private const FIRST_NAME_PATTERN = "/^[\\p{L}\\p{M}][\\p{L}\\p{M} '\\-]*$/u";

    public function index(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $membership = $this->membershipOf($member);

        return response()->json([
            'success' => true,
            'data' => [
                'can_add_children' => $this->isFather($member),
                'family_members' => $membership === null
                    ? []
                    : $membership->familyMembers()->orderBy('id')->get()
                        ->map(fn (FamilyMember $familyMember) => $this->present($familyMember, $this->isFather($member)))
                        ->all(),
            ],
        ]);
    }

    public function storeChild(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        if (! $this->isFather($member)) {
            return response()->json([
                'success' => false,
                'message' => 'Only a male member can add children.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $this->validateChild($request);

        $membership = $this->membershipOf($member);

        if ($membership === null) {
            throw ValidationException::withMessages([
                'first_name' => ['There is no membership to add a child to yet.'],
            ]);
        }

        $fullName = $this->fullNameFor($member, $validated['first_name']);

        $children = $membership->familyMembers()->whereIn('relationship', self::CHILD_RELATIONSHIPS);

        if ($children->count() >= self::MAX_CHILDREN) {
            throw ValidationException::withMessages([
                'first_name' => ['The limit for children added online has been reached. Please contact customer services.'],
            ]);
        }

        if ($membership->familyMembers()->where('name', $fullName)->exists()) {
            throw ValidationException::withMessages([
                'first_name' => ['This child has already been added.'],
            ]);
        }

        $familyMember = FamilyMember::create([
            'membership_id' => $membership->id,
            'name' => $fullName,
            'relationship' => $validated['relationship'],
            'is_active' => true,
        ]);

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_CREATED,
            oldValues: null,
            newValues: $this->snapshot($familyMember) + ['added_by' => 'member'],
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Child added.',
            'data' => ['family_member' => $this->present($familyMember, true)],
        ], Response::HTTP_CREATED);
    }

    public function updateChild(Request $request, string $familyMember): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        if (! $this->isFather($member)) {
            return $this->onlyFathers();
        }

        $validated = $this->validateChild($request);
        $membership = $this->membershipOf($member);
        $child = $this->childOf($membership, $familyMember);

        $fullName = $this->fullNameFor($member, $validated['first_name']);

        if ($membership->familyMembers()->where('name', $fullName)->whereKeyNot($child->id)->exists()) {
            throw ValidationException::withMessages([
                'first_name' => ['This child has already been added.'],
            ]);
        }

        $before = $this->snapshot($child);

        $child->update([
            'name' => $fullName,
            'relationship' => $validated['relationship'],
        ]);

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_UPDATED,
            oldValues: $before,
            newValues: $this->snapshot($child->fresh()) + ['changed_by' => 'member'],
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Child updated.',
            'data' => ['family_member' => $this->present($child->fresh(), true)],
        ]);
    }

    public function destroyChild(Request $request, string $familyMember): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        if (! $this->isFather($member)) {
            return $this->onlyFathers();
        }

        $membership = $this->membershipOf($member);
        $child = $this->childOf($membership, $familyMember);

        $before = $this->snapshot($child);

        $child->delete();

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_DELETED,
            oldValues: $before + ['removed_by' => 'member'],
            newValues: null,
            request: $request,
        );

        return response()->json(['success' => true, 'message' => 'Child removed.']);
    }

    /**
     * @return array{first_name: string, relationship: string}
     */
    private function validateChild(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:60', 'regex:'.self::FIRST_NAME_PATTERN],
            'relationship' => ['required', Rule::in(self::CHILD_RELATIONSHIPS)],
        ]);
    }

    private function fullNameFor(User $member, string $firstName): string
    {
        $firstName = preg_replace('/\s+/u', ' ', trim($firstName));

        return $firstName.' '.preg_replace('/\s+/u', ' ', trim((string) $member->name));
    }

    /**
     * One of THIS membership's sons or daughters, or a 404.
     */
    private function childOf(?Membership $membership, string $id): FamilyMember
    {
        abort_if($membership === null || ! ctype_digit($id), Response::HTTP_NOT_FOUND);

        return $membership->familyMembers()
            ->whereIn('relationship', self::CHILD_RELATIONSHIPS)
            ->findOrFail((int) $id);
    }

    private function onlyFathers(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Only a male member can change children.',
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FamilyMember $familyMember): array
    {
        return [
            'family_member_id' => $familyMember->id,
            'name' => $familyMember->name,
            'relationship' => $familyMember->relationship?->value,
        ];
    }

    /**
     * Whether this member may add children: male, and named — the child's full
     * name is theirs appended, so a father with no name on file has nothing to
     * append.
     */
    private function isFather(User $member): bool
    {
        return $member->gender === 'male' && trim((string) $member->name) !== '';
    }

    private function membershipOf(User $member): ?Membership
    {
        return $member->memberships()->latest('id')->first();
    }

    /**
     * `can_edit` is true for a son or a daughter of a father: the only people
     * this member may change or remove from here.
     *
     * @return array{id: int, name: string, relationship: ?string, relationship_label: ?string, is_active: bool, can_edit: bool}
     */
    private function present(FamilyMember $familyMember, bool $canEdit = false): array
    {
        return [
            'id' => $familyMember->id,
            'name' => $familyMember->name,
            'relationship' => $familyMember->relationship?->value,
            'relationship_label' => $familyMember->relationship?->label(),
            'is_active' => (bool) $familyMember->is_active,
            'can_edit' => $canEdit && in_array($familyMember->relationship?->value, self::CHILD_RELATIONSHIPS, true),
        ];
    }
}
