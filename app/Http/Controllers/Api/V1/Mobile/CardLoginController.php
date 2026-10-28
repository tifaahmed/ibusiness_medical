<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Partner\OtpAuthController;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use App\Services\Otp\MemberOtp;
use App\Services\Otp\MemberRegistration;
use App\Support\PhoneNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signing in to the app with a CARD NUMBER instead of a phone.
 *
 * The number alone is only a key that shows a card (`CardController`); to open the member's data it must be proved
 * by the phone attached to the card. So:
 *  - the card has a phone → a code is texted THERE (the app is only told the masked number) and, once typed, signs in;
 *  - the card has NO phone → the holder may ADD one (never change one): a code is texted to the new number and,
 *    once typed, the number is saved on the card's owner and they are signed in.
 * A phone that is already on the card is never replaced from here, and a phone that belongs to any other account
 * is refused, so the add-a-phone road can only fill an empty slot.
 *
 * Every answer carries a `reason` next to its `message`, like the phone sign-in. Throttled per IP at the route;
 * the per-number limits (cooldown, counted attempts) live in `MemberOtp`.
 */
class CardLoginController extends Controller
{
    public function __construct(
        private MemberOtp $otp,
        private MemberRegistration $registration,
        private OtpAuthController $signIn,
    ) {}

    /** Text a code to the phone on the card. A card with no phone gets none: the answer says `has_phone: false`. */
    public function request(Request $request): JsonResponse
    {
        $membership = $this->card($request);

        if (! $membership instanceof Membership) {
            return $this->unknownCard();
        }

        $phone = (string) $membership->user?->phone;

        if (PhoneNumbers::national($phone) === null) {
            return response()->json(['reason' => 'no_phone', 'message' => __('auth.otp.card_no_phone'), 'data' => ['has_phone' => false]]);
        }

        return $this->send($phone, true);
    }

    /** Check the code texted to the card's phone and sign in. */
    public function verify(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:12']])['code'];
        $membership = $this->card($request);

        if (! $membership instanceof Membership) {
            return $this->unknownCard();
        }

        $user = $membership->user;
        $phone = (string) $user?->phone;

        if (! $user instanceof User || PhoneNumbers::national($phone) === null) {
            return response()->json(['reason' => 'no_phone', 'message' => __('auth.otp.card_no_phone')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($failure = $this->rejected($phone, $code)) {
            return $failure;
        }

        return $this->signIn->signedIn($user);
    }

    /** Text a code to a NEW phone, to be added to a card that has none. */
    public function requestPhone(Request $request): JsonResponse
    {
        $phone = $request->validate(['phone' => ['required', 'string', 'max:32']])['phone'];
        $membership = $this->card($request);

        if (! $membership instanceof Membership) {
            return $this->unknownCard();
        }

        if ($refusal = $this->cannotAdd($membership, $phone)) {
            return $refusal;
        }

        return $this->send($phone, false);
    }

    /** Check the code texted to the new phone, save it on the card's owner (only if still empty) and sign in. */
    public function verifyPhone(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32'], 'code' => ['required', 'string', 'max:12']]);
        $membership = $this->card($request);

        if (! $membership instanceof Membership) {
            return $this->unknownCard();
        }

        if ($refusal = $this->cannotAdd($membership, $data['phone'])) {
            return $refusal;
        }

        if ($failure = $this->rejected($data['phone'], $data['code'])) {
            return $failure;
        }

        $national = PhoneNumbers::national($data['phone']);

        /* Locked and re-checked: two people racing to claim the same empty card must not both write a phone. */
        $user = DB::transaction(function () use ($membership, $national) {
            $user = User::query()->lockForUpdate()->find($membership->user_id);

            if (! $user instanceof User || PhoneNumbers::national((string) $user->phone) !== null) {
                return null;
            }

            $user->phone = $national;
            $user->save();

            return $user;
        });

        if (! $user instanceof User) {
            return response()->json(['reason' => 'phone_already_set', 'message' => __('auth.otp.card_has_phone')], Response::HTTP_CONFLICT);
        }

        return $this->signIn->signedIn($user);
    }

    /** The card behind the typed number: a real one (not a `REG-` placeholder), with its owner. */
    private function card(Request $request): ?Membership
    {
        $number = trim((string) $request->validate(['card_number' => ['required', 'string', 'max:64']])['card_number']);

        return Membership::visible()
            ->where('membership_number', $number)
            ->where('membership_number', 'not like', MemberRegistration::PENDING_PREFIX.'%')
            ->whereHas('user')
            ->with('user')
            ->first();
    }

    /** Why this phone cannot be added to this card, or null when it can. */
    private function cannotAdd(Membership $membership, string $phone): ?JsonResponse
    {
        if (PhoneNumbers::national($phone) === null) {
            return response()->json(['reason' => 'invalid_phone', 'message' => __('auth.otp.invalid_phone')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (PhoneNumbers::national((string) $membership->user?->phone) !== null) {
            return response()->json(['reason' => 'phone_already_set', 'message' => __('auth.otp.card_has_phone')], Response::HTTP_CONFLICT);
        }

        $national = PhoneNumbers::national($phone);
        $taken = User::query()
            ->whereRaw(User::phoneDigitsSql().' IN (?, ?, ?)', [$national, ltrim($national, '0'), '20'.ltrim($national, '0')])
            ->exists();

        if ($taken) {
            return response()->json(['reason' => 'phone_taken', 'message' => __('auth.otp.phone_taken')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /** Issue and send a code to `$phone`, with the same cooldown and failure answers as the phone sign-in. */
    private function send(string $phone, bool $hasPhone): JsonResponse
    {
        $wait = $this->otp->secondsUntilResend($phone);

        if ($wait > 0) {
            return response()->json(['reason' => 'cooldown', 'message' => __('auth.otp.cooldown'), 'retry_after' => $wait], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $issue = $this->otp->issue($phone);

        if ($issue['error'] !== null) {
            return response()->json(['reason' => 'not_configured', 'message' => __('auth.otp.not_configured')], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'reason' => 'sent',
            'message' => __('auth.otp.sent'),
            'data' => [
                'has_phone' => $hasPhone,
                'phone_masked' => $this->otp->maskPhone($phone),
                'delivery' => $issue['delivery'],
                'code_length' => $issue['length'],
                'expires_in' => $issue['expires_in'],
                'resend_after' => $issue['resend_after'],
            ],
        ]);
    }

    /** A wrong, expired or exhausted code as a response; null when the code is right. */
    private function rejected(string $phone, string $code): ?JsonResponse
    {
        $result = $this->otp->verify($phone, $code);

        if ($result['verified']) {
            return null;
        }

        return response()->json([
            'reason' => $result['reason'],
            'message' => match ($result['reason']) {
                'expired' => __('auth.otp.expired_code'),
                'too_many_attempts' => __('auth.otp.too_many_attempts'),
                default => __('auth.otp.invalid_code'),
            },
            'attempts_left' => $result['attempts_left'],
        ], $result['reason'] === 'too_many_attempts' ? Response::HTTP_TOO_MANY_REQUESTS : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function unknownCard(): JsonResponse
    {
        return response()->json(['reason' => 'unknown_card', 'message' => __('auth.otp.unknown_card')], Response::HTTP_NOT_FOUND);
    }
}
