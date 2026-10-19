<?php

namespace App\Http\Controllers\Admin\Order\Payment;

use App\Actions\Payment\CheckPaymobPayment;
use App\Actions\Payment\RefundPaymobPayment;
use App\Actions\Payment\SendOrderReceipt;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use App\Models\OrderLog;
use App\Services\Paymob\PaymobException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The admin's hands on an order's online payment: look it up at the gateway,
 * refund it, mail the receipt again. Each one leaves a row in `order_logs`
 * (who, when, what came of it) and, where it touches Paymob, the full
 * exchange in `order_payment_logs` — the order's "Payment" tab.
 */
class AdminOrderPaymentController extends BaseController
{
    /** Ask Paymob what became of the payment and apply the answer, as a callback would. */
    public function check(Request $request, string $order, CheckPaymobPayment $check): RedirectResponse
    {
        $orderModel = $this->find($order);

        if (! $orderModel->isOnlinePayment()) {
            return back()->with('error', 'This order is not paid through Paymob.');
        }

        try {
            $result = $check->handle($orderModel, Auth::id(), $request);

            return back()->with('success', "Paymob says: {$result['outcome']}. {$result['note']}");
        } catch (PaymobException $exception) {
            $this->logFailure('check', $orderModel, $exception, $request);
            OrderLog::record($orderModel->id, Auth::id(), OrderLog::ACTION_PAYMENT_CHECKED, null, [
                'ok' => false, 'error' => $exception->getMessage(),
            ], $request);

            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logFailure('check', $orderModel, $exception, $request);

            return back()->with('error', 'The payment could not be checked. See the application log.');
        }
    }

    /** Refund all or part of a paid order through the gateway. */
    public function refund(Request $request, string $order, RefundPaymobPayment $refund): RedirectResponse
    {
        $orderModel = $this->find($order);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $refund->handle($orderModel, round((float) $validated['amount'], 2), $validated['reason'] ?? null, (int) Auth::id(), $request);

            return back()->with('success', $result['note']);
        } catch (PaymobException $exception) {
            $this->logFailure('refund', $orderModel, $exception, $request);

            return back()->with('error', $exception->getMessage());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logFailure('refund', $orderModel, $exception, $request);

            return back()->with('error', 'The refund could not be completed. See the application log.');
        }
    }

    /** Mail the purchase receipt again (to the address on the order). */
    public function resendReceipt(Request $request, string $order, SendOrderReceipt $send): RedirectResponse
    {
        $orderModel = $this->find($order);
        $result = $send->handle($orderModel, Auth::id(), $request, force: true);

        return match (true) {
            $result['sent'] => back()->with('success', 'Receipt emailed to '.$orderModel->receiptEmail().'.'),
            $result['reason'] === 'no_email' => back()->with('error', 'This order has no email address to send a receipt to.'),
            default => back()->with('error', 'The receipt could not be sent. See the application log.'),
        };
    }

    private function find(string $code): Order
    {
        return Order::query()->where('order_code', $code)->firstOrFail();
    }

    private function logFailure(string $what, Order $order, Throwable $exception, Request $request): void
    {
        Log::error("Order payment {$what} failed.", [
            'route' => $request->path(),
            'order_code' => $order->order_code,
            'admin_id' => Auth::id(),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
