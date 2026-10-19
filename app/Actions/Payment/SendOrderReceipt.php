<?php

namespace App\Actions\Payment;

use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mails the purchase receipt to the buyer, when there is an address to mail.
 *
 * Never throws: the order is already committed, and a mail server that is down
 * is not a reason to fail a payment callback (Paymob would retry it) or to
 * tell a buyer their order did not go through. Sent or not, it is on the log.
 */
class SendOrderReceipt
{
    /**
     * @param  bool  $force  resend even if one already went (the admin's button)
     * @return array{sent: bool, reason: ?string}
     */
    public function handle(Order $order, ?int $adminId = null, ?Request $request = null, bool $force = false): array
    {
        $to = $order->receiptEmail();

        if ($to === null) {
            return ['sent' => false, 'reason' => 'no_email'];
        }

        if (! $force && $order->receipt_emailed_at !== null) {
            return ['sent' => false, 'reason' => 'already_sent'];
        }

        try {
            Mail::to($to)->send(new OrderReceiptMail($order->loadMissing('products')));

            $order->forceFill(['receipt_emailed_at' => now()])->save();

            OrderLog::record($order->id, $adminId, OrderLog::ACTION_RECEIPT_EMAILED, null, [
                'sent' => true,
                'to' => $to,
                'forced' => $force,
            ], $request);

            return ['sent' => true, 'reason' => null];
        } catch (Throwable $exception) {
            Log::error('Order receipt email could not be sent.', [
                'order_code' => $order->order_code,
                'to' => $to,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            try {
                OrderLog::record($order->id, $adminId, OrderLog::ACTION_RECEIPT_EMAILED, null, [
                    'sent' => false,
                    'to' => $to,
                    'error' => $exception->getMessage(),
                ], $request);
            } catch (Throwable) {
                // The failure is already in the application log.
            }

            return ['sent' => false, 'reason' => 'failed'];
        }
    }
}
