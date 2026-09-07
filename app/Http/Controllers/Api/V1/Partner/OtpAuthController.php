<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otp\MemberOtp;
use App\Services\Otp\MemberRegistration;
use App\Services\Otp\RegistrationProgress;
use App\Support\OtpSettings;
use App\Support\PhoneNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phone sign-in for the Deilar storefront: a number in, a code by SMS, a
 * Sanctum token back.
 *
 * There are no passwords in this flow because members do not have one they
 * remember — their membership is a card with a phone number attached, and the
 * phone is the thing they can prove they hold. Everything about the code is
 * decided here (whether one is sent at all, how long it is, what it says); the
 * storefront only relays what a visitor typed. See `App\Support\OtpSettings`.
 *
 * Key-gated like the rest of `/api/v1/partner/*`: the caller is another server,
 * and these two endpoints mint credentials.
 *
 * Every answer carries a `reason` alongside its `message`. The storefront shows
 * its own wording in the visitor's language and falls back to `message`, so a
 * new failure mode here does not ship as an untranslated string over there.
 */
class OtpAuthController extends Controller
{
    public function __construct(
        private MemberOtp $otp,
        private MemberRegistration $registration,
        private RegistrationProgress $progress,
    ) {}

    /**
     * Send a login code to the phone on a membership.
     */
    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $phone = $validated['phone'];

        if (PhoneNumbers::national($phone) === null) {
            return response()->json([
                'reason' => 'invalid_phone',
                'message' => __('auth.otp.invalid_phone'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /*
         * An unknown number is NOT refused. The storefront registers whoever
         * can answer a code sent to a phone, so the only difference an existing
         * member makes here is one flag in the answer — the code, the cooldown
         * and the attempt count are the same either way.
         */
        $member = $this->otp->findMember($phone);

        $wait = $this->otp->secondsUntilResend($phone);

        if ($wait > 0) {
            return response()->json([
                'reason' => 'cooldown',
                'message' => __('auth.otp.cooldown'),
                'retry_after' => $wait,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $issue = $this->otp->issue($phone);

        if ($issue['error'] !== null) {
            $configured = OtpSettings::deliversBySms() || OtpSettings::fixedCode() !== null;

            Log::warning('Member login code was not issued.', [
                'user_id' => $member?->id,
                'error' => $issue['error'],
            ]);

            /*
             * Two different failures wearing one status: an administrator has
             * closed the login, or the gateway would not take the message.
             * Both leave the visitor unable to sign in right now and neither is
             * anything they can fix, so both are 503 — the `reason` is what
             * tells them apart.
             */
            return response()->json([
                'reason' => $configured ? 'send_failed' : 'not_configured',
                'message' => $configured
                    ? __('auth.otp.send_failed')
                    : __('auth.otp.not_configured'),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'reason' => 'sent',
            'message' => $issue['delivery'] === 'sms'
                ? __('auth.otp.sent')
                : __('auth.otp.sent_fixed'),
            'data' => [
                /*
                 * The masked number, never the stored one: the caller sent a
                 * number a visitor typed, and answering with the full row would
                 * turn this into a way to read a member's phone off a guess.
                 */
                'phone_masked' => $this->otp->maskPhone($member?->phone ?? $phone),
                /*
                 * Whether verifying this code will CREATE an account. The
                 * storefront uses it for one line of copy on the code step, so
                 * somebody signing up for the first time is told what is about
                 * to happen rather than being dropped into a half-empty
                 * profile with no explanation.
                 */
                'registering' => ! $member instanceof User,
                /*
                 * 'sms' or 'fixed'. The storefront uses it for one line of copy
                 * ("we sent you a code" vs "enter your code") — it is never
                 * told what the code IS, in either mode.
                 */
                'delivery' => $issue['delivery'],
                'code_length' => $issue['length'],
                'expires_in' => $issue['expires_in'],
                'resend_after' => $issue['resend_after'],
            ],
        ]);
    }

    /**
     * Exchange a phone and a correct code for an API token.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $phone = $validated['phone'];

        if (PhoneNumbers::national($phone) === null) {
            return response()->json([
                'reason' => 'invalid_phone',
                'message' => __('auth.otp.invalid_phone'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $member = $this->otp->findMember($phone);

        /*
         * The code is checked BEFORE anything is created. A correct code is the
         * only proof this flow has that the person holds the phone, so nothing
         * exists until it has been given — a wrong guess against an unknown
         * number must not leave an account behind.
         */
        $result = $this->otp->verify($phone, $validated['code']);

        if (! $result['verified']) {
            $status = $result['reason'] === 'too_many_attempts'
                ? Response::HTTP_TOO_MANY_REQUESTS
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return response()->json([
                'reason' => $result['reason'],
                'message' => match ($result['reason']) {
                    'expired' => __('auth.otp.expired_code'),
                    'too_many_attempts' => __('auth.otp.too_many_attempts'),
                    default => __('auth.otp.invalid_code'),
                },
                'attempts_left' => $result['attempts_left'],
            ], $status);
        }

        $registered = false;

        if (! $member instanceof User) {
            /*
             * A correct code proves somebody holds the phone. It does not make
             * them a member of a number that belongs to STAFF — registering
             * there would attach a storefront membership to an account carrying
             * admin roles, and the token this hands back would carry them too.
             *
             * Answered as an unknown phone rather than as "that is an admin":
             * the storefront has no business being told whose number it is.
             */
            if ($this->registration->belongsToStaff($phone)) {
                return response()->json([
                    'reason' => 'unknown_phone',
                    'message' => __('auth.otp.unknown_phone'),
                ], Response::HTTP_NOT_FOUND);
            }

            /*
             * First time in. What is created is deliberately inert — an
             * inactive, invisible membership with a pending number — so signing
             * up buys nothing a card buys until staff have issued one. See
             * `MemberRegistration`.
             */
            $member = $this->registration->register($phone);
            $registered = true;
        }

        $membership = $member->memberships()->latest('id')->first();

        /*
         * A token per sign-in rather than one reused: a member signing out on
         * the storefront deletes the token they came in on
         * (`POST /api/v1/auth/logout`), and that must not sign out the phone in
         * their pocket at the same time.
         */
        $token = $member->createToken('storefront-phone-login')->plainTextToken;

        return response()->json([
            'reason' => 'verified',
            'message' => __('auth.otp.verified'),
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'avatar_url' => get_image_url($member, 'avatar'),
                ],
                'membership' => $membership ? [
                    'id' => $membership->id,
                    'membership_number' => $membership->membership_number,
                    'slug' => $membership->slug,
                    'is_active' => (bool) ($membership->is_active ?? false),
                    'expiration_date' => $membership->expiration_date?->format('Y-m-d'),
                ] : null,
                /*
                 * Whether this sign-in just created the account. The storefront
                 * sends a new member straight to the "finish your details" form
                 * on the strength of it, rather than to the account page they
                 * have nothing on yet.
                 */
                'registered' => $registered,
                /*
                 * The two progress bars, so the storefront can start nudging
                 * immediately without a second call. Worked out here because
                 * this application owns the fields being counted — see
                 * `RegistrationProgress`.
                 */
                'registration' => $this->progress->for($member),
            ],
        ]);
    }
}
