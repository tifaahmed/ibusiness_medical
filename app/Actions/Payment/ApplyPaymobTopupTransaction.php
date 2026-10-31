<?php

namespace App\Actions\Payment;

use App\Models\OrderPaymentLog;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\Paymob\PaymobTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What Paymob says about a WALLET TOP-UP, applied to it. The same rules as orders and subscriptions: idempotent, a wrong amount is never
 * accepted, a late failure never undoes a paid top-up, and the wallet is credited ONLY here, from a signed notice, once.
 */
class ApplyPaymobTopupTransaction
{
    /** @return array{outcome: string, changed: bool, note: string} */
    public function handle(WalletTopup $topup, PaymobTransaction $txn, string $source, string $correlationId, ?Request $request = null): array
    {
        $result = ['outcome' => $txn->outcome(), 'changed' => false, 'note' => ''];

        $topup->forceFill(array_filter(['paymob_order_id' => $txn->paymobOrderId, 'paymob_response_code' => $txn->responseCode], fn ($v) => $v !== null))->save();

        switch ($txn->outcome()) {
            case PaymobTransaction::PAID:
                $result = $this->paid($topup, $txn, $result);
                break;

            case PaymobTransaction::FAILED:
                if ($topup->status === WalletTopup::PAID) {
                    $result['note'] = 'A failed attempt was reported for a paid top-up — ignored.';
                    break;
                }
                $topup->forceFill(['status' => WalletTopup::REJECTED])->save();
                $result['changed'] = true;
                $result['note'] = 'Payment rejected: '.($txn->message ?? $txn->responseCode ?? 'declined').'.';
                break;

            case PaymobTransaction::REFUNDED:
            case PaymobTransaction::VOIDED:
                /* Taking money back out of a wallet is a decision for a person: flagged, not automated. */
                Log::warning('A paid wallet top-up was refunded at Paymob — review the wallet.', ['topup_id' => $topup->id]);
                $result['note'] = 'Refund recorded. The wallet was NOT reduced — review it.';
                break;

            default:
                $result['note'] = 'Payment is still pending at the gateway.';
        }

        $this->record($topup, $txn, $source, $correlationId, $request, $result);

        return $result;
    }

    /** @param array{outcome: string, changed: bool, note: string} $result */
    private function paid(WalletTopup $topup, PaymobTransaction $txn, array $result): array
    {
        $expected = (int) round(((float) $topup->amount) * 100);

        if ($txn->amountCents !== $expected) {
            $result['note'] = "Paid amount {$txn->amountCents} does not match the top-up ({$expected}). Not accepted.";

            return $result;
        }

        $credited = DB::transaction(function () use ($topup, $txn) {
            $locked = WalletTopup::query()->lockForUpdate()->findOrFail($topup->id);
            if ($locked->status === WalletTopup::PAID) {
                return false;
            }

            WalletTransaction::query()->create([
                'user_id' => $locked->user_id, 'source' => WalletTransaction::SOURCE_TOPUP, 'client_id' => 'topup:'.$locked->id,
                'amount' => $locked->amount, 'note' => 'top-up #'.$locked->id,
            ]);
            $locked->forceFill(['status' => WalletTopup::PAID, 'paid_at' => now(), 'paymob_transaction_id' => $txn->id])->save();

            return true;
        });

        $result['changed'] = $credited;
        $result['note'] = $credited ? 'Top-up paid; the wallet was credited.' : 'Already paid — nothing to do.';

        return $result;
    }

    /** @param array{outcome: string, changed: bool, note: string} $result */
    private function record(WalletTopup $topup, PaymobTransaction $txn, string $source, string $correlationId, ?Request $request, array $result): void
    {
        try {
            $now = now();
            OrderPaymentLog::create([
                'wallet_topup_id' => $topup->id, 'correlation_id' => $correlationId, 'event' => OrderPaymentLog::EVENT_APPLIED,
                'direction' => OrderPaymentLog::DIRECTION_IN, 'request_body' => json_encode(['source' => $source, 'outcome' => $result['outcome']]),
                'response_body' => $result['note'], 'gateway_code' => $txn->responseCode, 'gateway_ref' => $txn->id,
                'success' => $result['outcome'] === PaymobTransaction::PAID, 'started_at' => $now, 'finished_at' => $now, 'duration_ms' => 0,
                'ip_address' => $request?->ip(), 'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Top-up payment result could not be logged.', ['topup_id' => $topup->id, 'message' => $exception->getMessage()]);
        }
    }
}
