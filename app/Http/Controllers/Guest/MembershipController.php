<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Guest\MembershipResource;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends BaseController
{
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
            ->with(['user', 'company', 'partner', 'cardLayouts', 'familyMembers' => function ($query) {
                $query->where('is_active', true)->orderBy('created_at', 'asc');
            }, 'usages.facility', 'usages.facilityBranch', 'usages.facilityType', 'usages.media'])
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        return Inertia::render('Guest/Membership', [
            'membership' => (new MembershipResource($membershipModel))->toArray($request),
        ]);
    }
}
