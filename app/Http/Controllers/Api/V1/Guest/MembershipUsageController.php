<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Admin\MembershipUsage\Actions\Store\StoreMembershipUsageAction;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MembershipUsageController extends Controller
{
    public function __construct(private StoreMembershipUsageAction $storeAction) {}

    public function options(Request $request, string $membership): JsonResponse
    {
        $membershipModel = Membership::visible()
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $locale = app()->getLocale();
        $other = $locale === 'ar' ? 'en' : 'ar';

        $facilities = Facility::with('facilityType')->orderBy('id')->get()
            ->map(fn($f) => [
                'id' => $f->id,
                'name' => $f->getTranslation('name', $locale) ?: $f->getTranslation('name', $other),
                'facility_type_id' => $f->facility_type_id,
            ]);

        $facilityBranches = FacilityBranch::orderBy('facility_id')->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->getTranslation('name', $locale) ?: $b->getTranslation('name', $other),
                'facility_id' => $b->facility_id,
            ]);

        $facilityTypes = FacilityType::orderBy('id')->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->getTranslation('name', $locale) ?: $t->getTranslation('name', $other),
            ]);

        return response()->json([
            'membership' => [
                'id' => $membershipModel->id,
                'membership_number' => $membershipModel->membership_number,
                'slug' => $membershipModel->slug,
            ],
            'facilities' => $facilities,
            'facility_branches' => $facilityBranches,
            'facility_types' => $facilityTypes,
        ]);
    }

    public function store(Request $request, string $membership): JsonResponse
    {
        $membershipModel = Membership::visible()
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'facility_branch_id' => 'nullable|exists:facility_branches,id',
            'facility_type_id' => 'required|exists:facility_types,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'gallery' => 'nullable|array',
            'gallery.*' => 'image|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
        ]);

        $validated['membership_id'] = $membershipModel->id;

        try {
            $this->storeAction->execute($validated);

            return response()->json([
                'success' => true,
                'message' => __('home.membership_page.usage_create.saved'),
                'membership_slug' => $membershipModel->slug,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create guest MembershipUsage via API', ['error_message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('home.membership_page.usage_create.save_failed'),
            ], 500);
        }
    }
}
