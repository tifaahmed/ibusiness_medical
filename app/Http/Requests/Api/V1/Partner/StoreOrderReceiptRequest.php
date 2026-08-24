<?php

namespace App\Http\Requests\Api\V1\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The proof of a wallet transfer, sent after the order was placed.
 *
 * A separate request from the order itself because that is how buyers pay:
 * they place the order, open their wallet app, transfer, screenshot, and come
 * back. Requiring the receipt up front would lose the order.
 *
 * Two shapes, one endpoint: `receipt` for a single file and `receipts[]` for
 * several picked at once. The single form is what the storefront sent before
 * multiple uploads existed and is kept working rather than versioned away —
 * a partner mid-deploy must not have its uploads start failing.
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
    /**
     * Photographs and PDFs only, checked by MIME rather than by extension —
     * these files are served back to admins from our own domain.
     */
    public const ALLOWED_MIMETYPES = 'image/jpeg,image/png,image/webp,image/heic,application/pdf';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $file = [
            'file',
            'mimetypes:'.self::ALLOWED_MIMETYPES,
            'max:'.self::MAX_KILOBYTES,
        ];

        return [
            /*
             * Neither field is required on its own, and `required_without`
             * across the pair is what makes "send at least one" the actual
             * rule — a submission carrying neither is the only empty one.
             */
            'receipt' => ['required_without:receipts', ...$file],
            'receipts' => ['required_without:receipt', 'array', 'min:1'],
            'receipts.*' => $file,
            'reference' => ['nullable', 'string', 'max:190'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Every file this request carries, whichever shape it arrived in.
     *
     * @return list<UploadedFile>
     */
    public function receipts(): array
    {
        $files = $this->file('receipts');

        if (! is_array($files)) {
            $files = $files instanceof UploadedFile ? [$files] : [];
        }

        $single = $this->file('receipt');

        if ($single instanceof UploadedFile) {
            $files[] = $single;
        }

        return array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}
