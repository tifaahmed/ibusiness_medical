<?php

namespace App\Http\Requests\Admin\Order;

use App\Enums\Order\OrderStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateOrderStatusRequest extends FormRequest
{
    /**
     * How many orders one click may move.
     *
     * The list page tops out at 100 rows, so this covers "select the whole
     * page" with room to spare while keeping a hand-rolled post from asking
     * for a log write per order in the table.
     */
    public const MAX_ORDERS = 500;

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
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ORDERS],
            'ids.*' => ['integer', 'exists:orders,id'],
            'order_status' => ['required', Rule::in(OrderStatusEnum::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one order first.',
            'ids.max' => 'At most :max orders can be changed at once.',
            'ids.*.exists' => 'One of the selected orders no longer exists.',
            'order_status.required' => 'Choose the status to move these orders to.',
        ];
    }
}
