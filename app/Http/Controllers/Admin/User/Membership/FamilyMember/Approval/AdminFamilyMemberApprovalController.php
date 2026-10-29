<?php

namespace App\Http\Controllers\Admin\User\Membership\FamilyMember\Approval;

use App\Enums\FamilyMember\ApprovalStatusEnum;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Controllers\Concerns\FindsRelatives;
use App\Models\Membership;
use App\Models\MemberLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Staff's verdict on a family member added from the mobile app, and the
 * gated view of the ID scans that came with it.
 */
class AdminFamilyMemberApprovalController extends BaseController
{
    use FindsRelatives;
    use ScopesByMembershipCreator;

    public function approve(Request $request, string $user, string $membership, string $familyMember): RedirectResponse
    {
        $model = $this->find($user, $membership, $familyMember);
        $before = $this->snapshot($model);

        // is_active true also stamps approval_status (see Membership::guardRelative).
        $model->update([
            'is_active' => true,
            'approval_status' => ApprovalStatusEnum::APPROVED->value,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejection_reason' => null,
        ]);

        $this->record($request, $model, $before);

        return back()->with('success', 'Family member approved.');
    }

    public function reject(Request $request, string $user, string $membership, string $familyMember): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $model = $this->find($user, $membership, $familyMember);
        $before = $this->snapshot($model);

        $model->update([
            'is_active' => false,
            'approval_status' => ApprovalStatusEnum::REJECTED->value,
            'approved_at' => null,
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['reason'] ?? null,
        ]);

        $this->record($request, $model, $before);

        return back()->with('success', 'Family member rejected.');
    }

    /** Streams one ID scan; the files live on the private disk. */
    public function document(string $user, string $membership, string $familyMember, string $side): BinaryFileResponse
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);

        $model = $this->find($user, $membership, $familyMember);
        $media = $model->getFirstMedia("national_id_{$side}");

        abort_unless($media && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), ['Cache-Control' => 'private, no-store']);
    }

    private function find(string $user, string $membership, string $familyMember): Membership
    {
        return $this->findRelative($user, $membership, $familyMember);
    }

    private function record(Request $request, Membership $model, array $before): void
    {
        try {
            MemberLog::record(
                membershipId: $model->parent_membership_id,
                adminId: Auth::id(),
                action: MemberLog::ACTION_FAMILY_UPDATED,
                oldValues: $before,
                newValues: $this->snapshot($model->fresh(['user'])),
                request: $request,
            );
        } catch (\Throwable $e) {
            Log::warning('Family approval audit log failed', ['family_member_id' => $model->id, 'error' => $e->getMessage()]);
        }
    }

    private function snapshot(Membership $fm): array
    {
        return [
            'family_member_id' => $fm->id,
            'name' => $fm->user?->name,
            'is_active' => (bool) $fm->is_active,
            'approval_status' => $fm->approval_status,
            'rejection_reason' => $fm->rejection_reason,
        ];
    }
}
