<?php

namespace App\Http\Requests\Admin\Order;

use App\Enums\Address\AddressTypeEnum;
use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Http\Requests\Api\V1\Partner\StoreOrderReceiptRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
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
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string', 'max:65535'],

            /* The delivery address in detail — correctable by an admin, unlike
               the provenance columns, because this is where the courier goes. */
            'customer_address_type' => ['nullable', Rule::enum(AddressTypeEnum::class)],
            'customer_street' => ['nullable', 'string', 'max:255'],
            'customer_governorate' => ['nullable', 'string', 'max:255'],
            'customer_city' => ['nullable', 'string', 'max:255'],
            'customer_building_number' => ['nullable', 'string', 'max:50'],
            'customer_apartment_number' => ['nullable', 'string', 'max:50'],
            'customer_floor_number' => ['nullable', 'string', 'max:50'],
            'customer_special_mark' => ['nullable', 'string', 'max:500'],

            'notes' => ['nullable', 'string', 'max:65535'],
            'membership_number' => ['nullable', 'string', 'max:255'],

            'payment_status' => ['required', Rule::in(PaymentStatusEnum::values())],
            'delivery_status' => ['required', Rule::in(DeliveryStatusEnum::values())],

            /* `sometimes`, unlike its two neighbours: the column arrived after
               this form did, and a caller posting the older shape should leave
               the outcome alone rather than have the save refused. */
            'order_status' => ['sometimes', Rule::in(OrderStatusEnum::values())],
            'payment_type' => ['required', Rule::in(PaymentTypeEnum::values())],

            /*
             * A canceled order has to say why. The column exists for exactly
             * this, and "canceled, reason unknown" is the answer nobody can act
             * on three weeks later when the customer calls back.
             */
            'cancel_reason' => [
                Rule::requiredIf(fn () => $this->input('payment_status') === PaymentStatusEnum::CANCELED->value),
                'nullable',
                'string',
                'max:255',
            ],

            'total_paid' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'total_amount_before_discount' => ['nullable', 'numeric', 'min:0'],

            /*
             * Delivery. The profit is not accepted from the form: it is always
             * `price - cost`, worked out where the order is written, so the
             * three columns cannot be saved contradicting each other.
             */
            'delivery_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'delivery_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'source' => ['nullable', 'string', 'max:32'],

            /*
             * The lines, authoritative when present: a row missing from the
             * array is a line the admin removed. Absent altogether (a status-only
             * PATCH from somewhere else) leaves the archive untouched.
             */
            'products' => ['sometimes', 'array'],
            'products.*.id' => ['nullable', 'integer', 'exists:order_products,id'],
            'products.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'products.*.name' => ['required', 'array', 'min:1'],
            'products.*.name.*' => ['nullable', 'string', 'max:255'],
            'products.*.slug' => ['nullable', 'string', 'max:255'],
            'products.*.image' => ['nullable', 'string', 'max:2048'],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'products.*.old_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.new_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.profit_price' => ['nullable', 'numeric', 'min:0'],

            /*
             * Receipts, added alongside everything else on one save. Same file
             * rules as the buyer's upload endpoint — these are served back to
             * admins from our own domain — and no cap: an order takes as many
             * as the evidence needs.
             *
             * There is deliberately no `remove_receipt_ids` counterpart. The
             * collection is append-only for the admin exactly as it is for the
             * buyer — see `Order::RECEIPT_COLLECTION`. An admin who disagrees
             * with a receipt moves `payment_status`, which is attributed and
             * dated in `order_logs`; deleting the evidence is not attributable
             * at all.
             */
            'receipts' => ['sometimes', 'array'],
            'receipts.*' => [
                'file',
                'mimetypes:'.StoreOrderReceiptRequest::ALLOWED_MIMETYPES,
                'max:'.StoreOrderReceiptRequest::MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancel_reason.required' => 'A canceled order needs a cancel reason.',
            'products.*.name.required' => 'Every line needs a product name.',
            'products.*.quantity.required' => 'Every line needs a quantity.',
            'products.*.quantity.min' => 'A line quantity must be at least 1.',
            'products.*.id.exists' => 'One of the lines is no longer part of this order.',
            'products.*.product_id.exists' => 'One of the selected products no longer exists.',
            'receipts.*.mimetypes' => 'A receipt must be a photo (JPEG, PNG, WebP, HEIC) or a PDF.',
            'receipts.*.max' => 'A receipt must be under :max kilobytes.',
        ];
    }

    /**
     * A name typed into one language only still has to reach the model as a
     * translation map, and an empty string is not a translation — it is a
     * language the admin left blank.
     */
    protected function prepareForValidation(): void
    {
        $products = $this->input('products');

        if (! is_array($products)) {
            return;
        }

        $this->merge([
            'products' => array_map(function ($line) {
                if (is_array($line) && is_array($line['name'] ?? null)) {
                    $line['name'] = array_filter(
                        $line['name'],
                        fn ($text) => is_string($text) && trim($text) !== '',
                    );
                }

                return $line;
            }, $products),
        ]);
    }
}
