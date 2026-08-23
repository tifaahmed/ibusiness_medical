<?php

namespace App\Http\Controllers\Admin\User\Membership\Address\Update;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\Address\UpdateAddressRequest;
use App\Models\Address;
use App\Models\MemberLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminAddressUpdateController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Update the specified address.
     */
    public function __invoke(
        UpdateAddressRequest $request,
        string $user,
        string $membership,
        string $address
    ): RedirectResponse {
        $validated = $request->validated();

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

            $oldSnapshot = $this->snapshot($addressModel);

            // Update the address
            $addressModel->update([
                'type' => $validated['type'],
                'address' => $validated['address'] ?? null,
                'street' => $validated['street'] ?? null,
                'governorate_id' => $validated['governorate_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'building_number' => $validated['building_number'] ?? null,
                'apartment_number' => $validated['apartment_number'] ?? null,
                'floor_number' => $validated['floor_number'] ?? null,
                'special_mark' => $validated['special_mark'] ?? null,
            ]);

            $newSnapshot = $this->snapshot($addressModel->fresh());

            // Audit log (only if something actually changed)
            if ($oldSnapshot !== $newSnapshot) {
                MemberLog::record(
                    membershipId: $addressModel->membership_id,
                    adminId: Auth::id(),
                    action: MemberLog::ACTION_ADDRESS_UPDATED,
                    oldValues: $oldSnapshot,
                    newValues: $newSnapshot,
                    request: $request,
                );
            }

            Log::info('Address updated successfully', [
                'address_id' => $addressModel->id,
                'membership_id' => $addressModel->membership_id,
                'type' => $addressModel->type->value,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Address updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update address', [
                'address_id' => $address,
                'membership_slug' => $membership,
                'user_slug' => $user,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update address. Please try again.'])
                ->withInput();
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
