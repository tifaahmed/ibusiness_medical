<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A signed-in member's own addresses, read-only.
 *
 * Token-gated like the orders and the family: `$request->user()` is the whole
 * authorisation story and no membership id is read from the request. The
 * membership is the member's LATEST one, active or not, for the same reason the
 * profile reads it — a self-registered member holds a pending card until staff
 * issue a real one and must still see what is on it.
 *
 * Only what is worth drawing is returned, not the admin resource: no
 * membership id, no timestamps.
 */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $membership = $member->memberships()->latest('id')->first();

        $addresses = $membership === null
            ? collect()
            : $membership->addresses()->with(['governorate', 'city'])->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'addresses' => $addresses->map(fn (Address $address) => $this->present($address))->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Address $address): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $address->id,
            'type' => $address->type?->value,
            'type_label' => $address->type?->label(),
            'address' => $address->address,
            'street' => $address->street,
            'building_number' => $address->building_number,
            'floor_number' => $address->floor_number,
            'apartment_number' => $address->apartment_number,
            'special_mark' => $address->special_mark,
            'governorate_id' => $address->governorate_id,
            'governorate_name' => $address->governorate?->getTranslation('name', $locale) ?: $address->governorate?->getTranslation('name', 'en'),
            'city_id' => $address->city_id,
            'city_name' => $address->city?->getTranslation('name', $locale) ?: $address->city?->getTranslation('name', 'en'),
        ];
    }
}
