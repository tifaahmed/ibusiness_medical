<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Actions\Contact\RecordContactMessageAction;
use App\Actions\Membership\StartSubscription;
use App\Actions\Membership\StartTrialCard;
use App\Enums\Contact\ContactChannelEnum;
use App\Enums\Contact\ContactSourceEnum;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\User;
use App\Services\Paymob\PaymobException;
use App\Support\MembershipStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The signed-in member's own card: ask for a trial, see its state, pay for it,
 * and ask customer services to add relatives.
 *
 * Token-gated; the card is always the caller's own latest one and no membership
 * id is read from the request.
 */
class MembershipController extends Controller
{
    public function __construct(
        private readonly StartTrialCard $trial,
        private readonly StartSubscription $subscribe,
        private readonly RecordContactMessageAction $inbox,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => MembershipStatus::forMember($request->user())]);
    }

    /** A free trial card from a phone, a name and a national ID — nothing else. */
    public function trial(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:190'],
            'national_id' => ['required', 'regex:/^[23]\d{13}$/'],
        ], [
            'national_id.regex' => 'The national ID must be 14 digits.',
        ]);

        try {
            $membership = $this->trial->handle($user, $validated['name'], $validated['national_id'], $request);
        } catch (ValidationException $exception) {
            Log::info('Trial card refused.', ['user_id' => $user->id, 'reason' => $exception->errorBag, 'errors' => array_keys($exception->errors())]);

            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'reason' => $exception->errorBag === 'default' ? 'invalid' : $exception->errorBag,
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $exception) {
            Log::error('Trial card could not be issued.', [
                'route' => $request->path(),
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['message' => 'The card could not be created. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['data' => MembershipStatus::forMember($user->refresh())], Response::HTTP_CREATED);
    }

    /** Start paying: Paymob returns a checkout URL; the others reach customer services. */
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'method' => ['required', Rule::in(MembershipSubscription::methods())],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'return_url' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:190'],
            'receipts' => ['nullable', 'array', 'max:10'],
            'receipts.*' => ['file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:8192'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:8192'],
        ]);

        $membership = $user->memberships()->latest('id')->first();

        if ($membership === null || $membership->state() === Membership::STATE_PENDING) {
            return response()->json(['message' => 'Create your card first.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $files = array_values(array_filter([
            ...($request->file('receipts') ?? []),
            $request->file('receipt'),
        ]));

        try {
            $result = $this->subscribe->handle(
                $membership,
                $validated['method'],
                $validated['email'] ?? null,
                $validated['return_url'] ?? null,
                $files,
                $validated['reference'] ?? null,
                $request,
            );
        } catch (PaymobException $exception) {
            Log::error('Membership payment could not be opened.', [
                'route' => $request->path(), 'membership_id' => $membership->id, 'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'The payment page could not be opened. Please try again.'], Response::HTTP_BAD_GATEWAY);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Membership subscription failed.', [
                'route' => $request->path(), 'membership_id' => $membership->id,
                'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Your request could not be saved. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => [
                'subscription_id' => $result['subscription']->id,
                'method' => $result['subscription']->method,
                'checkout_url' => $result['checkout_url'],
                /* For the embedded card form (Paymob Pixel); the hosted page above is the fallback. */
                'client_secret' => $result['client_secret'],
                'public_key' => $result['public_key'],
                'status' => MembershipStatus::forMember($user),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * "Please call me to add my relatives" — the trial member's only way to add
     * any, until they pay. Lands in the contact inbox (and its mail) as its own
     * source so sales can work it as a call-back list.
     */
    public function requestRelatives(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $user->memberships()->latest('id')->first();

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        if ($membership === null || $membership->state() === Membership::STATE_PENDING) {
            return response()->json(['message' => 'Create your card first.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /* One open request per card is enough; a member pressing twice must not flood the inbox. */
        $already = \App\Models\ContactMessage::query()
            ->where('source', ContactSourceEnum::RELATIVE_REQUEST->value)
            ->where('phone', $user->phone)
            ->whereIn('status', ['new', 'in_progress'])
            ->exists();

        if (! $already) {
            try {
                $this->inbox->handle([
                    'phone' => (string) $user->phone,
                    'name' => $user->name,
                    'email' => $user->email,
                    'subject' => 'Add relatives — card '.$membership->membership_number,
                    'message' => 'Card '.$membership->membership_number.' ('.($membership->isTrial() ? 'trial, ends '.$membership->expiration_date?->toDateString() : 'paid').') asks to be called to add relatives.'
                        .(filled($validated['note'] ?? null) ? ' Note: '.$validated['note'] : ''),
                    'source' => ContactSourceEnum::RELATIVE_REQUEST->value,
                    'channel' => ContactChannelEnum::fromRequest($request)->value,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            } catch (Throwable $exception) {
                Log::error('Relatives request could not be filed.', [
                    'route' => $request->path(), 'user_id' => $user->id,
                    'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString(),
                ]);

                return response()->json(['message' => 'Your request could not be sent. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return response()->json(['data' => ['requested' => true, 'already_open' => $already]]);
    }
}
