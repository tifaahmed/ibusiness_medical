<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Support\WalletWithdrawals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cash wallet money out to Vodafone Cash (`App\Support\WalletWithdrawals` owns every rule).
 *
 *  GET  /wallet/withdrawals              the rules (min / max / switch) and my requests, newest first
 *  POST /wallet/withdrawals              { amount, msisdn, national_id } — the wallet is debited at once
 *  POST /wallet/withdrawals/{id}/cancel  only while it still waits for approval; the money comes back
 */
class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'rules' => WalletWithdrawals::summary(),
            'withdrawals' => WalletWithdrawal::query()->where('user_id', $user->id)->latest('id')->limit(50)->get()->map(fn (WalletWithdrawal $w) => $this->present($w))->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'msisdn' => ['required', 'string', 'max:20'],
            'national_id' => ['required', 'string', 'max:20'],
        ]);

        $result = WalletWithdrawals::request($user, (float) $data['amount'], $data['msisdn'], $data['national_id']);

        if (! $result['ok']) {
            $messages = [
                'disabled' => 'Cashing out is not available right now.',
                'bad_amount' => 'The amount is outside the allowed range ('.WalletWithdrawals::min().' – '.WalletWithdrawals::max().' EGP).',
                'bad_number' => 'Enter a Vodafone Cash number (it starts with 010).',
                'bad_national_id' => 'Enter your 14-digit national ID.',
                'not_enough' => 'Your wallet does not hold this amount.',
                'daily_limit' => 'This is above what you can cash out in 24 hours.',
                'open_request' => 'You already have a cash-out request in progress.',
            ];

            return response()->json(['message' => $messages[$result['reason']] ?? 'The request could not be made.', 'reason' => $result['reason']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->present($result['withdrawal'])], Response::HTTP_CREATED);
    }

    public function cancel(Request $request, WalletWithdrawal $withdrawal): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless((int) $withdrawal->user_id === (int) $user->id, 404);

        if (! WalletWithdrawals::cancel($withdrawal, $user)) {
            return response()->json(['message' => 'This request can no longer be cancelled.', 'reason' => 'not_pending'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->present($withdrawal->fresh())]);
    }

    /** What the member sees: never the national ID, never Paymob's raw answer. */
    private function present(WalletWithdrawal $w): array
    {
        return [
            'id' => $w->id, 'amount' => (float) $w->amount, 'msisdn' => $w->maskedMsisdn(), 'status' => $w->status,
            'created_at' => $w->created_at?->toIso8601String(), 'paid_at' => $w->paid_at?->toIso8601String(),
            'refunded' => $w->refunded_at !== null,
        ];
    }
}
