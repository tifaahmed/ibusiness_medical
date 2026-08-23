<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions\Address;

use App\Models\Address;
use App\Models\City;
use App\Models\Governorate;
use App\Models\MemberLog;
use App\Models\Membership;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UpsertMemberAddressFromOrderAction
{
    /**
     * Mirror an order's delivery address into the buyer member's address book.
     *
     * An order placed with a membership number carries the buyer's address;
     * writing it back here is what keeps the card's address book current —
     * the next checkout can prefill from it and the courier sees one truth.
     * The rule is type-keyed:
     *   - an address of the SAME type already on the member  → updated in place,
     *   - no address of that type                            → a new row is added.
     *
     * Orders archive the governorate/city as free text, addresses keep FKs, so
     * the text is resolved by name; when it does not resolve the existing ids
     * are left alone rather than wiped — an unmatchable spelling must not
     * erase a known location.
     *
     * Deliberately forgiving: a sync failure never fails the order that
     * triggered it. Callers still wrap this, but it returns null instead of
     * throwing for every "nothing to do" case.
     */
    public function execute(Order $order, ?Request $request = null): ?Address
    {
        /* No membership number or no typed address on the order — nothing to
           key the write on (a legacy order, or an anonymous checkout). */
        if (! $order->membership_number || $order->customer_address_type === null) {
            return null;
        }

        if (! $this->hasDetail($order)) {
            Log::info('Order address not synced to member: order carries no address detail.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'membership_number' => $order->membership_number,
            ]);

            return null;
        }

        $membership = Membership::query()
            ->matchingNumber($order->membership_number)
            ->first();

        if ($membership === null) {
            /* Not fatal — the number may be stale or mistyped at checkout.
               The order itself is fine; only the mirror is skipped. */
            Log::warning('Order address not synced to member: membership number matches no member.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'membership_number' => $order->membership_number,
            ]);

            return null;
        }

        $type = $order->customer_address_type;

        $attributes = [
            'address' => $order->customer_address,
            'street' => $order->customer_street,
            'governorate_id' => $this->resolveGovernorateId($order),
            'city_id' => null,
            'building_number' => $order->customer_building_number,
            'apartment_number' => $order->customer_apartment_number,
            'floor_number' => $order->customer_floor_number,
            'special_mark' => $order->customer_special_mark,
        ];

        /** @var Address|null $address */
        $address = $membership->addresses()
            ->where('type', $type->value)
            ->first();

        if ($address !== null) {
            /* Text that does not resolve must not blank out a known
               governorate/city — keep whatever the row already holds. */
            $attributes['governorate_id'] = $attributes['governorate_id']
                ?? $address->governorate_id;
            $attributes['city_id'] = $this->resolveCityId($order, $attributes['governorate_id'])
                ?? $address->city_id;

            $oldSnapshot = $this->snapshot($address);
            $address->update($attributes);
            $newSnapshot = $this->snapshot($address);

            if ($oldSnapshot !== $newSnapshot) {
                MemberLog::record(
                    membershipId: $membership->id,
                    adminId: null,
                    action: MemberLog::ACTION_ADDRESS_UPDATED,
                    oldValues: $oldSnapshot,
                    newValues: $newSnapshot,
                    request: $request,
                );
            }

            Log::info('Order address synced onto the member\'s existing address.', [
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'membership_id' => $membership->id,
                'address_id' => $address->id,
                'address_type' => $type->value,
            ]);

            return $address;
        }

        $attributes['type'] = $type->value;
        $attributes['city_id'] = $this->resolveCityId($order, $attributes['governorate_id']);

        /** @var Address $address */
        $address = $membership->addresses()->create($attributes);

        MemberLog::record(
            membershipId: $membership->id,
            adminId: null,
            action: MemberLog::ACTION_ADDRESS_CREATED,
            oldValues: null,
            newValues: $this->snapshot($address),
            request: $request,
        );

        Log::info('Order address added to the member\'s address book as a new entry.', [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'membership_id' => $membership->id,
            'address_id' => $address->id,
            'address_type' => $type->value,
        ]);

        return $address;
    }

    /**
     * Whether the order carries any real address detail beyond the bare type —
     * syncing an all-blank edit would wipe the member's actual address with
     * nothing in its place.
     */
    private function hasDetail(Order $order): bool
    {
        return collect([
            $order->customer_address,
            $order->customer_street,
            $order->customer_governorate,
            $order->customer_city,
            $order->customer_building_number,
            $order->customer_apartment_number,
            $order->customer_floor_number,
            $order->customer_special_mark,
        ])->some(fn ($value) => filled($value));
    }

    /**
     * Resolve the order's archived governorate text back to an id, matching
     * against either translation. Null when nothing matches — callers decide
     * whether that means "leave the old value" or "no value yet".
     */
    private function resolveGovernorateId(Order $order): ?int
    {
        $text = trim((string) $order->customer_governorate);

        if ($text === '') {
            return null;
        }

        $governorate = Governorate::query()
            ->where(fn ($query) => $query
                ->where('name->en', 'like', "%{$text}%")
                ->orWhere('name->ar', 'like', "%{$text}%"))
            ->first();

        if ($governorate === null) {
            Log::info('Order governorate text did not resolve to a governorate record.', [
                'order_id' => $order->id,
                'customer_governorate' => $text,
            ]);
        }

        return $governorate?->id;
    }

    /**
     * Same resolution for the city, scoped inside the resolved governorate so
     * two governorates sharing a city name cannot pick the wrong row.
     */
    private function resolveCityId(Order $order, ?int $governorateId): ?int
    {
        $text = trim((string) $order->customer_city);

        if ($text === '' || $governorateId === null) {
            return null;
        }

        return City::query()
            ->where('governorate_id', $governorateId)
            ->where(fn ($query) => $query
                ->where('name->en', 'like', "%{$text}%")
                ->orWhere('name->ar', 'like', "%{$text}%"))
            ->first()?->id;
    }

    /**
     * @return array<string, mixed>
     */
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
