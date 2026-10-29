<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Actions\Contact\RecordContactMessageAction;
use App\Enums\Contact\ContactChannelEnum;
use App\Enums\Contact\ContactSourceEnum;
use App\Http\Controllers\Controller;
use App\Support\MembershipPlan;
use App\Enums\FamilyMember\ApprovalStatusEnum;
use App\Enums\FamilyMember\RelationshipEnum;
use App\Actions\Membership\CreateRelative;
use App\Actions\Membership\UpdateRelative;
use App\Services\MediaService;
use Illuminate\Support\Facades\Log;
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
    public function __construct(
        private MediaService $mediaService,
        private RecordContactMessageAction $inbox,
        private CreateRelative $createRelative,
    ) {}

    /**
     * The most children a member can add for themselves. A ceiling rather than
     * a rule about families: it stops a loop from filling a card.
     */
    private const MAX_CHILDREN = MembershipPlan::MAX_RELATIVES;

    /** First degree only: parents, spouse and children. Siblings are second degree — customer services adds those. */
    private const FIRST_DEGREE = ['wife', 'husband', 'son', 'daughter', 'father', 'mother'];

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
                    : $membership->relatives()->with('user')->orderBy('id')->get()
                        ->map(fn (Membership $relative) => $this->present($relative, true))
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

        if ($refusal = $this->refusal($membership)) {
            return $refusal;
        }

        $fullName = $this->fullNameFor($member, $validated['first_name']);

        $children = $membership->relatives()->whereIn('relationship', self::CHILD_RELATIONSHIPS);

        if ($children->count() >= self::MAX_CHILDREN) {
            throw ValidationException::withMessages([
                'first_name' => ['The limit for children added online has been reached. Please contact customer services.'],
            ]);
        }

        if ($membership->relatives()->whereHas('user', fn ($q) => $q->where('name', $fullName))->exists()) {
            throw ValidationException::withMessages([
                'first_name' => ['This child has already been added.'],
            ]);
        }

        // Anything added from the app waits for staff (CreateRelative files it pending for a member).
        $familyMember = $this->createRelative->handle($membership, [
            'name' => $fullName,
            'relationship' => $validated['relationship'],
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
        ], Membership::ADDED_BY_MEMBER, null, $request);

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_CREATED,
            oldValues: null,
            newValues: $this->snapshot($familyMember) + ['added_by' => 'member'],
            request: $request,
        );

        $this->tellAdmins($member, $membership, $familyMember, $request);

        return response()->json([
            'success' => true,
            'message' => 'Child added.',
            'data' => ['family_member' => $this->present($familyMember->load('user'), true)],
        ], Response::HTTP_CREATED);
    }

    /**
     * Any relative, added from the app. Needs the national ID number, both
     * faces of the ID card and a photo of the person — all required — and
     * waits for staff approval before it counts on the card.
     */
    public function storeMember(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:'.self::FIRST_NAME_PATTERN],
            'relationship' => ['required', Rule::in(self::FIRST_DEGREE)],
            'national_id' => ['required', 'digits:14'],
            'phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'id_front' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'id_back' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
        ]);

        $membership = $this->membershipOf($member);

        if ($membership === null) {
            throw ValidationException::withMessages([
                'name' => ['There is no membership to add a member to yet.'],
            ]);
        }

        if ($refusal = $this->refusal($membership)) {
            return $refusal;
        }

        try {
            $familyMember = $this->createRelative->handle($membership, [
                'name' => $validated['name'],
                'relationship' => $validated['relationship'],
                'national_id' => $validated['national_id'],
                'phone' => $validated['phone'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
            ], Membership::ADDED_BY_MEMBER, null, $request);

            $this->mediaService->uploadImage($familyMember, $request->file('photo'), 'photo');
            $this->mediaService->uploadImage($familyMember, $request->file('id_front'), 'national_id_front', 'local');
            $this->mediaService->uploadImage($familyMember, $request->file('id_back'), 'national_id_back', 'local');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Member family add failed', [
                'user_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($familyMember)) {
                $user = $familyMember->user;
                $familyMember->forceDelete();
                $user?->forceDelete();
            }

            return response()->json(['success' => false, 'message' => 'Could not save the family member. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_CREATED,
            oldValues: null,
            newValues: $this->snapshot($familyMember) + ['added_by' => 'member', 'approval_status' => 'pending'],
            request: $request,
        );

        $this->tellAdmins($member, $membership, $familyMember, $request);

        return response()->json([
            'success' => true,
            'message' => 'Family member sent for approval.',
            'data' => ['family_member' => $this->present($familyMember->fresh('user'), false)],
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

        if ($membership->relatives()->whereHas('user', fn ($q) => $q->where('name', $fullName))->whereKeyNot($child->id)->exists()) {
            throw ValidationException::withMessages([
                'first_name' => ['This child has already been added.'],
            ]);
        }

        $before = $this->snapshot($child);

        $child->user->update(['name' => $fullName]);
        $child->update(['relationship' => $validated['relationship']]);

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_UPDATED,
            oldValues: $before,
            newValues: $this->snapshot($child->fresh('user')) + ['changed_by' => 'member'],
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Child updated.',
            'data' => ['family_member' => $this->present($child->fresh('user'), true)],
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

        $child->user?->delete();
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
     * The card owner completes a relative's data. A relative still waiting for (or refused by) staff can be edited freely;
     * an APPROVED one keeps its name and anything already filled in (customer service changes those), so only EMPTY phone /
     * birth date / National ID can be added. A new phone is saved unverified.
     */
    public function updateRelative(Request $request, string $familyMember): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'],
            'national_id' => ['sometimes', 'nullable', 'digits:14'],
        ]);

        $membership = $this->membershipOf($member);
        $relative = $this->relativeOf($membership, $familyMember);
        $user = $relative->user;

        $changes = [];

        foreach (['name' => $user?->name, 'phone' => $user?->phone, 'date_of_birth' => $user?->date_of_birth?->format('Y-m-d'), 'national_id' => $relative->national_id] as $field => $current) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $value = is_string($validated[$field]) ? trim($validated[$field]) : $validated[$field];
            $value = $value === '' ? null : $value;

            if ($value === ($current === '' ? null : $current)) {
                continue;
            }

            if ($this->isApproved($relative) && ($field === 'name' || filled($current))) {
                throw ValidationException::withMessages([
                    $field => ['This data is already approved. Please contact customer service to change it.'],
                ]);
            }

            $changes[$field] = $value;
        }

        if ($changes === []) {
            return response()->json(['success' => true, 'message' => 'Nothing to change.', 'data' => ['family_member' => $this->present($relative, true)]]);
        }

        $before = $this->snapshot($relative);

        app(UpdateRelative::class)->handle($relative, $changes);

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_UPDATED,
            oldValues: $before,
            newValues: $this->snapshot($relative->fresh('user')) + ['changed_by' => 'member', 'fields' => array_keys($changes)],
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Relative updated.',
            'data' => ['family_member' => $this->present($relative->fresh('user'), true)],
        ]);
    }

    /** The card owner sets (or replaces) the profile photo of one of their relatives. A photo is not identity data, so an approved relative may get one too. */
    public function updateRelativeAvatar(Request $request, string $familyMember): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048']]);

        /** @var User $member */
        $member = $request->user();
        $relative = $this->relativeOf($this->membershipOf($member), $familyMember);
        $user = $relative->user;

        abort_if($user === null, Response::HTTP_NOT_FOUND);

        // Not a single-file collection and the reader takes the first media: clear the old photo or it keeps being served.
        $user->clearMediaCollection('avatar');
        $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return response()->json([
            'success' => true,
            'message' => 'Photo updated.',
            'data' => ['avatar_url' => get_image_url($user, 'avatar')],
        ]);
    }

    /** The card owner removes a relative from their card (any relation, waiting or approved). */
    public function destroyRelative(Request $request, string $familyMember): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $membership = $this->membershipOf($member);
        $relative = $this->relativeOf($membership, $familyMember);

        $before = $this->snapshot($relative);

        $relative->user?->delete();
        $relative->delete();

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_FAMILY_DELETED,
            oldValues: $before + ['removed_by' => 'member'],
            newValues: null,
            request: $request,
        );

        return response()->json(['success' => true, 'message' => 'Relative removed.']);
    }

    /** One of THIS card's relatives of any relation, or a 404. */
    private function relativeOf(?Membership $membership, string $id): Membership
    {
        abort_if($membership === null || ! ctype_digit($id), Response::HTTP_NOT_FOUND);

        return $membership->relatives()->with('user')->findOrFail((int) $id);
    }

    private function isApproved(Membership $relative): bool
    {
        return ($relative->approval_status ?: ApprovalStatusEnum::APPROVED->value) === ApprovalStatusEnum::APPROVED->value;
    }

    /**
     * @return array{first_name: string, relationship: string, date_of_birth?: ?string, phone?: ?string, national_id?: ?string}
     */
    private function validateChild(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:60', 'regex:'.self::FIRST_NAME_PATTERN],
            'relationship' => ['required', Rule::in(self::CHILD_RELATIONSHIPS)],
            // A child still needs at least one of the three (checked when the relative is created).
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'national_id' => ['nullable', 'digits:14'],
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
    private function childOf(?Membership $membership, string $id): Membership
    {
        abort_if($membership === null || ! ctype_digit($id), Response::HTTP_NOT_FOUND);

        return $membership->relatives()->with('user')
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
    private function snapshot(Membership $familyMember): array
    {
        return [
            'family_member_id' => $familyMember->id,
            'name' => $familyMember->user?->name,
            'relationship' => $familyMember->relationship,
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

    /**
     * Why this card may not take another relative right now, or null if it may.
     *
     * Three separate reasons, each with its own `reason` for the client to word:
     * a TRIAL card holds the owner alone (relatives go through customer
     * services until it is paid), an expired card adds nothing, and fifteen is
     * the ceiling for one card.
     */
    private function refusal(Membership $membership): ?JsonResponse
    {
        if ($membership->isTrial()) {
            return response()->json([
                'success' => false,
                'reason' => 'trial_no_relatives',
                'message' => 'Relatives cannot be added during the trial. Please contact customer service and they will add them for you.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $membership->isCurrent()) {
            return response()->json([
                'success' => false,
                'reason' => 'card_expired',
                'message' => 'Your card has expired. Renew it to add relatives.',
            ], Response::HTTP_FORBIDDEN);
        }

        $counted = $membership->relatives()->where('approval_status', '!=', ApprovalStatusEnum::REJECTED->value)->count();

        if ($counted >= MembershipPlan::MAX_RELATIVES) {
            return response()->json([
                'success' => false,
                'reason' => 'limit_reached',
                'message' => 'A card carries up to '.MembershipPlan::MAX_RELATIVES.' relatives. Please contact customer service.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /**
     * A relative added by the card owner waits for an admin: say so in the
     * contact inbox (and its mail), so it is not found only by chance. A mail
     * failure is the inbox action's to swallow — the relative is already saved.
     */
    private function tellAdmins(User $member, Membership $membership, Membership $familyMember, Request $request): void
    {
        try {
            $this->inbox->handle([
                'phone' => (string) $member->phone,
                'name' => $member->name,
                'email' => $member->email,
                'subject' => 'Relative awaiting approval — card '.$membership->membership_number,
                'message' => 'Card '.$membership->membership_number.' added '.$familyMember->user?->name.' ('.($familyMember->relationship ?? '').') and is waiting for approval.',
                'source' => ContactSourceEnum::PENDING_RELATIVE->value,
                'channel' => ContactChannelEnum::fromRequest($request)->value,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Pending relative could not be announced to the admins.', [
                'membership_id' => $membership->id,
                'family_member_id' => $familyMember->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function membershipOf(User $member): ?Membership
    {
        return $member->memberships()->latest('id')->first();
    }

    /**
     * `can_edit` is true for a son or a daughter of a father: the only people
     * this member may change or remove from here.
     *
     * @return array{id: int, name: string, relationship: ?string, relationship_label: ?string, is_active: bool, approval_status: string, rejection_reason: ?string, can_edit: bool}
     */
    private function present(Membership $familyMember, bool $canEdit = false): array
    {
        $relationship = $familyMember->relationship ? RelationshipEnum::tryFrom($familyMember->relationship) : null;

        return [
            'id' => $familyMember->id,
            'name' => $familyMember->user?->name,
            'relationship' => $familyMember->relationship,
            'relationship_label' => $relationship?->label(),
            'membership_number' => $familyMember->membership_number,
            'phone' => $familyMember->user?->phone,
            'phone_verified' => $familyMember->user?->phone_verified_at !== null,
            'date_of_birth' => $familyMember->user?->date_of_birth?->format('Y-m-d'),
            'national_id' => $familyMember->national_id,
            // The owner may fill in what is missing on any relative and remove any of them; an approved one keeps its name and filled data (staff change those).
            'can_delete' => $canEdit,
            'locked' => $this->isApproved($familyMember),
            'is_active' => (bool) $familyMember->is_active,
            'approval_status' => $familyMember->approval_status ?: ApprovalStatusEnum::APPROVED->value,
            'rejection_reason' => $familyMember->rejection_reason,
            'can_edit' => $canEdit,
        ];
    }
}
