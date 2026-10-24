<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Actions\Membership\StartSubscription;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Services\Paymob\PaymobException;
use App\Support\MembershipStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * What a QR-code scan of a card leads to: the card's state, and a way to pay
 * for it — without signing in.
 *
 * Public on purpose. A member whose trial just ended is looking at a red
 * "expired" sign on a card they scanned, and the shortest road from there to
 * paying must not run through a login. It is safe because paying for somebody
 * else's card helps nobody to anything: the response is a checkout URL, and
 * the status carries no name, phone or ID. Only Paymob is offered here — the
 * manual methods need a person to follow up and go through the signed-in route.
 */
class CardSubscriptionController extends Controller
{
    public function status(string $number): JsonResponse
    {
        $membership = $this->find($number);

        return response()->json(['data' => MembershipStatus::forCard($membership)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function subscribe(Request $request, string $number, StartSubscription $subscribe): JsonResponse
    {
        $membership = $this->find($number);

        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'return_url' => ['nullable', 'string', 'max:500'],
            'paymob_channel' => ['nullable', 'in:card,wallet'],
        ]);

        try {
            $result = $subscribe->handle(
                $membership,
                MembershipSubscription::METHOD_PAYMOB,
                $validated['email'] ?? null,
                $validated['return_url'] ?? null,
                [],
                null,
                $request,
            );
        } catch (ValidationException $exception) {
            return response()->json(['message' => collect($exception->errors())->flatten()->first()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PaymobException $exception) {
            Log::error('Card payment could not be opened.', [
                'route' => $request->path(), 'membership_id' => $membership->id, 'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'The payment page could not be opened. Please try again.'], Response::HTTP_BAD_GATEWAY);
        } catch (Throwable $exception) {
            Log::error('Card payment failed.', [
                'route' => $request->path(), 'membership_id' => $membership->id,
                'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Something went wrong. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['data' => ['checkout_url' => $result['checkout_url'], 'client_secret' => $result['client_secret'], 'public_key' => $result['public_key'], 'form' => $result['form']]], Response::HTTP_CREATED);
    }

    private function find(string $number): Membership
    {
        return Membership::visible()->matchingNumber($number)->firstOrFail();
    }
}
