<?php

namespace App\Http\Controllers\Admin\User\Membership\Address\Delete;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Address;
use App\Models\MemberLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminAddressDeleteController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Delete the specified address.
     */
    public function __invoke(
        Request $request,
        string $user,
        string $membership,
        string $address
    ): RedirectResponse {
        try {
            // Find the address
            $addressModel = Address::with('membership')->whereHas('membership.user', function ($query) use ($user) {
                $query->where('slug', $user);
            })->whereHas('membership', function ($query) use ($membership) {
                $query->where('slug', $membership);
            })->where('id', $address)->firstOrFail();

            if ($addressModel->membership) {
                $this->assertCanManageMembership($addressModel->membership);
            }

            $addressId = $addressModel->id;
            $oldSnapshot = $this->snapshot($addressModel);
            $membershipId = $addressModel->membership_id;

            // Delete the address (soft delete)
            $addressModel->delete();

            // Audit log
            MemberLog::record(
                membershipId: $membershipId,
                adminId: Auth::id(),
                action: MemberLog::ACTION_ADDRESS_DELETED,
                oldValues: $oldSnapshot,
                newValues: null,
                request: $request,
            );

            Log::info('Address deleted successfully', [
                'address_id' => $addressId,
                'membership_id' => $membershipId,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Address deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete address', [
                'address_id' => $address,
                'membership_slug' => $membership,
                'user_slug' => $user,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete address. Please try again.']);
        }
    }

    private function snapshot(Address $address): array
    {
        return [
            'address_id' => $address->id,
            'type' => $address->type?->value,
            'address' => $address->address,
            'street' => $address->street,
            'governorate_id' => $address->governorate_id,
            'city_id' => $address->city_id,
            'building_number' => $address->building_number,
            'apartment_number' => $address->apartment_number,
            'floor_number' => $address->floor_number,
            'special_mark' => $address->special_mark,
        ];
    }
}
