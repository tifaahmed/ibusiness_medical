<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions\Address;

use App\Enums\Address\AddressTypeEnum;
use App\Models\Address;
use App\Models\MemberLog;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UpsertMemberAddressAction
{
    /**
     * Write the address posted with the member create/update form onto the
     * membership's PRIMARY address row — the first one, creating it when the
     * member has none.
     *
     * The Addresses tab stays the place for extra addresses; this keeps the
     * main form and that tab editing the same row rather than forking a second
     * "official" one. Only the governorate is required by validation, so the
     * rest arrive as whatever the admin left blank.
     *
     * @param  array<string, mixed>  $data  validated request data
     */
    public function execute(Membership $membership, array $data, ?Request $request = null): Address
    {
        $attributes = [
            'type' => $data['address_type'] ?? AddressTypeEnum::HOME->value,
            'address' => $data['address'] ?? null,
            'street' => $data['street'] ?? null,
            /* Governorate is validated as required, but a caller that skips
               validation must not crash the save here. */
            'governorate_id' => $data['governorate_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'building_number' => $data['building_number'] ?? null,
            'apartment_number' => $data['apartment_number'] ?? null,
            'floor_number' => $data['floor_number'] ?? null,
            'special_mark' => $data['special_mark'] ?? null,
        ];

        $address = $membership->addresses()->first();

        if ($address === null) {
            /** @var Address $address */
            $address = $membership->addresses()->create($attributes);

            MemberLog::record(
                membershipId: $membership->id,
                adminId: Auth::id(),
                action: MemberLog::ACTION_ADDRESS_CREATED,
                oldValues: null,
                newValues: $this->snapshot($address),
                request: $request,
            );

            Log::info('Member primary address created.', [
                'membership_id' => $membership->id,
                'address_id' => $address->id,
                'admin_id' => Auth::id(),
            ]);

            return $address;
        }

        $oldSnapshot = $this->snapshot($address);
        $address->update($attributes);
        $newSnapshot = $this->snapshot($address);

        if ($oldSnapshot !== $newSnapshot) {
            MemberLog::record(
                membershipId: $membership->id,
                adminId: Auth::id(),
                action: MemberLog::ACTION_ADDRESS_UPDATED,
                oldValues: $oldSnapshot,
                newValues: $newSnapshot,
                request: $request,
            );
        }

        Log::info('Member primary address updated.', [
            'membership_id' => $membership->id,
            'address_id' => $address->id,
            'admin_id' => Auth::id(),
        ]);

        return $address;
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
