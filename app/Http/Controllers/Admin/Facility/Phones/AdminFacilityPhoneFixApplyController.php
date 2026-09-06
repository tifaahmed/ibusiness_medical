<?php

namespace App\Http\Controllers\Admin\Facility\Phones;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Support\PhoneNumbers;
use App\Support\PhoneRepair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Writes the correction for one branch, the row the admin pressed confirm on.
 *
 * What gets stored is what the admin has in front of them: the suggestion is
 * editable, so a number the rules could only guess at — or could not repair at
 * all — can be retyped before it is confirmed. The list is still put through
 * the same splitting and digit folding as every other save, so a pasted pair
 * cannot go in as one entry.
 */
class AdminFacilityPhoneFixApplyController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:facility_branches,id'],
            'phones' => ['present', 'array'],
            // Generous on the way in: a pasted cell holding two numbers is
            // split below, and the real limit is checked on what comes out.
            'phones.*.number' => ['required', 'string', 'max:120'],
            'phones.*.type' => ['nullable', Rule::in(FacilityBranch::PHONE_TYPES)],
        ], [
            'phones.*.number.required' => 'A number cannot be left blank — remove the row instead.',
            'phones.*.number.max' => 'That is too long to be a phone number.',
        ]);

        $branch = FacilityBranch::with('facility')->findOrFail($validated['branch_id']);

        if ($branch->facility) {
            $this->assertOwns($branch->facility);
        }

        $stored = PhoneRepair::stored($branch);
        $before = PhoneNumbers::entries($stored);
        $after = PhoneNumbers::entries($validated['phones']);

        $tooLong = array_filter(
            $after,
            fn (array $entry) => mb_strlen($entry['number']) > PhoneNumbers::MAX_LENGTH,
        );

        if ($tooLong !== []) {
            throw ValidationException::withMessages([
                'phones' => 'Each number must be '.PhoneNumbers::MAX_LENGTH.' characters or fewer: '
                    .implode(', ', array_column($tooLong, 'number')),
            ]);
        }

        // Two questions, because either can be the thing that is wrong: are
        // these different numbers, and is the column still holding several of
        // them in one cell?
        $unchanged = $after === $before
            && array_column($after, 'number') === PhoneRepair::repair($stored)['current'];

        if ($unchanged) {
            return response()->json([
                'applied' => false,
                'message' => 'Nothing to change — those are the numbers already stored.',
                'phones' => $before,
            ]);
        }

        $branch->phone = $after ?: null;
        $branch->save();

        FacilityBranchLog::record(
            facilityBranchId: $branch->id,
            facilityId: $branch->facility_id,
            adminId: Auth::id(),
            action: FacilityBranchLog::ACTION_UPDATED,
            oldValues: ['phone' => $before],
            newValues: ['phone' => $after],
            request: $request,
        );

        FacilityLog::record(
            facilityId: $branch->facility_id,
            adminId: Auth::id(),
            action: FacilityLog::ACTION_BRANCH_UPDATED,
            oldValues: ['branch_id' => $branch->id, 'phone' => $before],
            newValues: ['branch_id' => $branch->id, 'phone' => $after],
            request: $request,
        );

        return response()->json([
            'applied' => true,
            'message' => 'Phone numbers saved.',
            'phones' => $after,
        ]);
    }
}
