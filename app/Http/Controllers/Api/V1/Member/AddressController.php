<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Enums\Address\AddressTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * A signed-in member's own addresses: read, and save one typed at checkout.
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
     * Save the address a member typed at checkout onto their own card.
     *
     * Type-keyed, like the order mirror: an address of the SAME type already on
     * the card is updated in place, otherwise a new row is added. Only the keys
     * posted are written, so a form that left a box out never blanks it.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $data = $request->validate([
            'type' => ['required', Rule::enum(AddressTypeEnum::class)],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'street' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'building_number' => ['nullable', 'string', 'max:50'],
            'floor_number' => ['nullable', 'string', 'max:50'],
            'apartment_number' => ['nullable', 'string', 'max:50'],
            'special_mark' => ['nullable', 'string', 'max:255'],
        ]);

        $membership = $member->memberships()->latest('id')->first();

        if ($membership === null) {
            return response()->json(['success' => false, 'message' => 'No card to save the address on.'], 404);
        }

        try {
            $address = $membership->addresses()->updateOrCreate(
                ['type' => $data['type']],
                collect($data)->except('type')->all(),
            );
        } catch (\Throwable $exception) {
            Log::error('Member address could not be saved.', [
                'route' => $request->path(),
                'membership_id' => $membership->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['success' => false, 'message' => 'Address could not be saved.'], 500);
        }

        Log::info('Member address saved from checkout.', [
            'membership_id' => $membership->id,
            'address_id' => $address->id,
            'type' => $data['type'],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['address' => $this->present($address->load(['governorate', 'city']))],
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
