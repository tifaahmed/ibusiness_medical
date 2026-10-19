<?php

namespace App\Actions\Payment;

use App\Models\Order;
use App\Services\Paymob\PaymobClient;
use App\Services\Paymob\PaymobException;
use App\Services\Paymob\PaymobTransaction;
use Illuminate\Http\Request;

/**
 * The "check with Paymob" button: ask the gateway what became of the payment
 * and apply the answer exactly as a callback would be. It is how an order
 * whose callback never arrived (server down, firewall, wrong URL) is repaired.
 */
class CheckPaymobPayment
{
    public function __construct(private readonly ApplyPaymobTransaction $apply) {}

    /**
     * @return array{outcome: string, changed: bool, note: string}
     *
     * @throws PaymobException
     */
    public function handle(Order $order, ?int $adminId, ?Request $request): array
    {
        $client = new PaymobClient($adminId, $request);

        $transaction = PaymobTransaction::fromArray($client->inquire($order));

        return $this->apply->handle($order, $transaction, 'inquiry', $client->correlationId, $adminId, $request);
    }
}
