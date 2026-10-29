<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Controllers\Concerns\ProvidesCardTemplates;
use App\Http\Resources\Guest\MembershipResource;
use App\Actions\Membership\StartSubscription;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Services\Paymob\PaymobException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends BaseController
{
    use ProvidesCardTemplates;
    /**
     * Handle membership number lookup and redirect.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $request->validate([
            'membership_number' => 'required|string',
        ], [
            'membership_number.required' => __('home.member_card_modal.required'),
        ]);

        $membership = Membership::visible()
            ->where(function ($query) use ($request) {
                $query->where('membership_number', $request->membership_number)
                    ->orWhere('slug', $request->membership_number);
            })
            ->first();

        if (! $membership) {
            return back()->withErrors([
                'membership_number' => __('home.member_card_modal.not_found'),
            ])->withInput();
        }

        // Redirect to the membership show page using the slug
        return redirect()->route('guest.membership.show', $membership->slug);
    }

    /**
     * Display the membership details page.
     */
    public function show(Request $request, string $membership): Response
    {
        $membershipModel = Membership::visible()
            ->with(['user', 'company', 'partner', 'cardLayouts.cardTemplate', 'relatives' => function ($query) {
                $query->with('user')->where('is_active', true)->orderBy('created_at', 'asc');
            }, 'usages.facility', 'usages.facilityBranch', 'usages.facilityType', 'usages.media'])
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        return Inertia::render('Guest/Membership', [
            'membership' => (new MembershipResource($membershipModel))->toArray($request),
            'cardTemplates' => $this->cardTemplatesByStatus(),
        ]);
    }

    /**
     * "Buy the membership" from the scan page of an expired (or trial) card.
     *
     * Public like the page itself: paying for a card helps nobody to anything,
     * and the road from a red "expired" sign to the checkout must not run
     * through a login. Only Paymob is offered here; cash and transfers go
     * through customer services.
     */
    public function subscribe(Request $request, string $membership, StartSubscription $subscribe)
    {
        $model = Membership::visible()->matchingNumber($membership)->firstOrFail();

        try {
            $result = $subscribe->handle(
                $model,
                MembershipSubscription::METHOD_PAYMOB,
                $request->validate(['email' => ['nullable', 'email:rfc', 'max:190']])['email'] ?? null,
                null,
                [],
                null,
                $request,
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors(['subscribe' => collect($exception->errors())->flatten()->first()]);
        } catch (PaymobException $exception) {
            Log::error('Card payment could not be opened.', [
                'route' => $request->path(), 'membership_id' => $model->id, 'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['subscribe' => 'The payment page could not be opened. Please try again.']);
        }

        return Inertia::location($result['checkout_url']);
    }
}
