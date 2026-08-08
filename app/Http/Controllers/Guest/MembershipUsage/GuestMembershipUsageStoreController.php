<?php

namespace App\Http\Controllers\Guest\MembershipUsage;

use App\Http\Controllers\Admin\MembershipUsage\Actions\Store\StoreMembershipUsageAction;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GuestMembershipUsageStoreController extends BaseController
{
    public function __construct(private StoreMembershipUsageAction $storeAction) {}

    public function __invoke(Request $request, string $membership): RedirectResponse
    {
        $membershipModel = Membership::visible()
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'facility_id'        => 'required|exists:facilities,id',
            'facility_branch_id' => 'nullable|exists:facility_branches,id',
            'facility_type_id'   => 'required|exists:facility_types,id',
            'amount'             => 'required|numeric|min:0',
            'description'        => 'nullable|string|max:1000',
            'gallery'            => 'nullable|array',
            'gallery.*'          => 'image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
        ]);

        $validated['membership_id'] = $membershipModel->id;

        try {
            $this->storeAction->execute($validated);

            return redirect()
                ->route('guest.membership.show', $membershipModel->slug)
                ->with('success', __('home.membership_page.usage_create.saved'));
        } catch (\Exception $e) {
            Log::error('Failed to create guest MembershipUsage', ['error_message' => $e->getMessage()]);

            return back()->withErrors(['error' => __('home.membership_page.usage_create.save_failed')])->withInput();
        }
    }
}
