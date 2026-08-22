<?php

namespace App\Http\Requests\Api\V1\Partner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The proof of a wallet transfer, sent after the order was placed.
 *
 * A separate request from the order itself because that is how buyers pay:
 * they place the order, open their wallet app, transfer, screenshot, and come
 * back. Requiring the receipt up front would lose the order.
 */
class StoreOrderReceiptRequest extends FormRequest
{
    /**
     * A phone screenshot with nothing compressed away is comfortably under
     * this; anything above it is not a receipt.
     */
    public const MAX_KILOBYTES = 5120;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Photographs and PDFs only, checked by MIME rather than by
             * extension — this file is served back to admins from our own
             * domain.
             */
            'receipt' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/heic,application/pdf',
                'max:'.self::MAX_KILOBYTES,
            ],
            'reference' => ['nullable', 'string', 'max:190'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
