<?php

namespace App\Actions\Payment;

use App\Models\User;
use App\Models\WalletTopup;
use App\Services\Paymob\PaymobClient;
use App\Services\Paymob\PaymobException;
use App\Support\WalletPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/** A member starts adding money to their wallet: opens a Paymob checkout for the amount. The wallet is credited only by the signed callback. */
class StartWalletTopup
{
    /** The most one top-up may be (EGP). */
    public const MAX = 20000;

    /**
     * @return array{topup: WalletTopup, checkout_url: string, client_secret: string, public_key: ?string, form: string}
     *
     * @throws ValidationException
     * @throws PaymobException
     */
    public function handle(User $user, float $amount, ?string $returnUrl, ?Request $request = null): array
    {
        $amount = round($amount, 2);

        if ($amount < WalletPayments::minTopup()) {
            throw ValidationException::withMessages(['amount' => ['The amount is below the smallest top-up ('.WalletPayments::minTopup().' EGP).']]);
        }

        if ($amount > self::MAX) {
            throw ValidationException::withMessages(['amount' => ['The amount is above the largest top-up ('.self::MAX.' EGP).']]);
        }

        $topup = WalletTopup::query()->create(['user_id' => $user->id, 'amount' => $amount, 'status' => WalletTopup::PENDING]);
        $reference = $topup->nextPaymobReference();

        $allowed = StartPaymobPayment::allowedReturnUrl($returnUrl);
        if ($allowed !== null) {
            Cache::put(StartPaymobPayment::returnKey('TOP'.$topup->id), $allowed, now()->addHours(48));
        }

        $client = new PaymobClient(null, $request);

        try {
            $intention = $client->createTopupIntention($topup, $reference, route('api.v1.paymob.callback'), route('api.v1.paymob.return', ['code' => 'TOP'.$topup->id]));
        } catch (PaymobException $exception) {
            $topup->forceFill(['paymob_attempts' => 1, 'paymob_reference' => $reference, 'paymob_response_code' => 'intention_failed'])->save();

            throw $exception;
        }

        $topup->forceFill(['paymob_attempts' => 1, 'paymob_reference' => $reference, 'paymob_order_id' => $intention['intention_id'] ?? null])->save();

        return ['topup' => $topup, 'checkout_url' => $intention['checkout_url'], 'client_secret' => $intention['client_secret'], 'public_key' => config('services.paymob.public_key'), 'form' => $intention['form']];
    }
}
