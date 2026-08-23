<?php

namespace App\Http\Controllers\Admin\User\Membership\Address\Store;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\Address\StoreAddressRequest;
use App\Models\Address;
use App\Models\MemberLog;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminAddressStoreController extends BaseController
{
    use ScopesByMembershipCreator;

    /**
     * Store a newly created address.
     */
    public function __invoke(
        StoreAddressRequest $request,
        string $user,
        string $membership
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            // Find the membership
            $membershipModel = Membership::whereHas('user', function ($query) use ($user) {
                $query->where('slug', $user);
            })->where('slug', $membership)->firstOrFail();

            $this->assertCanManageMembership($membershipModel);

            // Create the address
            $address = Address::create([
                'membership_id' => $membershipModel->id,
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

            // Audit log
            MemberLog::record(
                membershipId: $membershipModel->id,
                adminId: Auth::id(),
                action: MemberLog::ACTION_ADDRESS_CREATED,
                oldValues: null,
                newValues: $this->snapshot($address->fresh()),
                request: $request,
            );

            Log::info('Address created successfully', [
                'address_id' => $address->id,
                'membership_id' => $membershipModel->id,
                'user_id' => $membershipModel->user_id,
                'type' => $address->type->value,
            ]);

            return redirect()->route('admin.user.membership.edit', $user)
                ->with('success', 'Address created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create address', [
                'membership_slug' => $membership,
                'user_slug' => $user,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create address. Please try again.'])
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
