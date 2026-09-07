<?php

namespace App\Http\Requests\Admin\Order;

use Illuminate\Foundation\Http\FormRequest;

class ShipOrderRequest extends FormRequest
{
    /**
     * The route already gates on `manage orders|manage own orders`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * The two fields the whole preview exists for. They are REQUIRED
             * and they come from the admin, never from the order: our columns
             * hold free Arabic text the buyer typed and ABS routes on numeric
             * ids, so an unconfirmed guess here is a parcel sent to the wrong
             * governorate. No default, no fallback — if the admin did not
             * choose, nothing ships.
             */
            'governorate_id' => ['required', 'integer', 'min:1'],
            'city_id' => ['required', 'integer', 'min:1'],

            /*
             * Optional corrections, each a field an admin might reasonably fix
             * on the way out: what the courier collects, what the manifest
             * says is inside, and anything the driver needs told.
             *
             * `cash` is `present`-friendly rather than `filled`: zero is a real
             * instruction ("collect nothing, it is paid"), not a missing value.
             */
            'cash' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'contents' => ['nullable', 'string', 'max:500'],
            'special_instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'governorate_id.required' => 'Choose the ABS governorate this order is being delivered to.',
            'city_id.required' => 'Choose the ABS city this order is being delivered to.',
        ];
    }
}
