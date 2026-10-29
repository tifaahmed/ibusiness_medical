<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A member deleting their own account from the app (App Store guideline 5.1.1(v)).
 *
 * The holder of a PRIMARY card takes every relative on it with them — the relatives
 * (`parent_membership_id`), and the login of a relative that
 * existed only for that card. A relative who signs in deletes only themselves: the
 * card they belong to stays. Orders and payments are kept (accounting), detached from
 * nothing but a soft-deleted user. Every deletion is written to the member log.
 */
class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['confirm' => ['required', 'accepted']]);

        /** @var User $member */
        $member = $request->user();

        try {
            $removed = DB::transaction(fn () => $this->erase($member, $request));
        } catch (\Throwable $e) {
            Log::error('Account deletion failed', ['user_id' => $member->id, 'exception' => $e]);

            return response()->json(['success' => false, 'message' => 'Could not delete the account. Please contact customer service.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Account deleted.', 'data' => ['removed_relatives' => $removed]]);
    }

    /** @return int how many relatives went with the account */
    private function erase(User $member, Request $request): int
    {
        $removedRelatives = 0;
        $relativeUserIds = [];

        foreach ($member->memberships()->get() as $membership) {
            if (! $membership->isRelative()) {
                $relatives = Membership::query()->where('parent_membership_id', $membership->id)->get();

                foreach ($relatives as $relative) {
                    if ($relative->user_id && $relative->user_id !== $member->id) {
                        $relativeUserIds[] = $relative->user_id;
                    }

                    $this->log($relative, 'relative', $request, $member);
                    $relative->delete();
                    $removedRelatives++;
                }
            }

            $this->log($membership, 'account', $request, $member);
            $membership->delete();
        }

        // A relative's login that served only this card goes too; one that holds a card of its own stays.
        User::query()->whereIn('id', array_unique($relativeUserIds))->get()->each(function (User $relativeUser) {
            if (! $relativeUser->memberships()->exists()) {
                $relativeUser->tokens()->delete();
                $relativeUser->delete();
            }
        });

        $member->deleteProfilePhoto();
        $member->tokens()->delete();
        $member->delete();

        return $removedRelatives;
    }

    private function log(Membership $membership, string $what, Request $request, User $member, array $extra = []): void
    {
        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_DELETED,
            oldValues: ['membership_number' => $membership->membership_number, 'member_type' => $membership->member_type] + $extra,
            newValues: ['deleted' => $what, 'removed_by' => 'member', 'user_id' => $member->id],
            request: $request,
        );
    }
}
