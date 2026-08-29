<?php

namespace App\Http\Controllers\Admin\Facility\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Services\FacilityEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Backs the "Fix English fields with AI" button on the admin facility form.
 * Called over axios; corrects and saves the English translations of this one
 * facility and its branches, then answers JSON.
 */
class AdminFacilityEnglishFixController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __construct(private readonly FacilityEnglishBackfiller $backfiller) {}

    public function __invoke(Request $request, string $facility): JsonResponse
    {
        $model = Facility::with([
            'facilityType',
            'branches.governorate',
            'branches.city',
        ])->where('slug', $facility)->firstOrFail();

        $this->assertOwns($model);

        try {
            $result = $this->backfiller->fix($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
