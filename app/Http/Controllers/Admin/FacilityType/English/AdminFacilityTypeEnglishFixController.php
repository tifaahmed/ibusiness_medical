<?php

namespace App\Http\Controllers\Admin\FacilityType\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\FacilityType;
use App\Services\FacilityTypeEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Backs the "Fix English with AI" button on the admin facility type edit form.
 * Called over axios; fills or repairs the English name of this one facility
 * type from its Arabic value, then answers JSON.
 */
class AdminFacilityTypeEnglishFixController extends BaseController
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

    public function __construct(private readonly FacilityTypeEnglishBackfiller $backfiller) {}

    public function __invoke(Request $request, string $facilityType): JsonResponse
    {
        $model = FacilityType::where('slug', $facilityType)->firstOrFail();

        $this->assertOwns($model);

        try {
            $result = $this->backfiller->fix($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
