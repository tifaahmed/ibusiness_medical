<?php

namespace App\Http\Controllers\Admin\Governorate\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Governorate;
use App\Services\GovernorateEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Backs the "Fix English with AI" button on the admin governorate edit form.
 * Called over axios; fills or repairs the English name of this one
 * governorate from its Arabic value, then answers JSON.
 */
class AdminGovernorateEnglishFixController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_GOVERNORATES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_GOVERNORATES;
    }

    public function __construct(private readonly GovernorateEnglishBackfiller $backfiller) {}

    public function __invoke(Request $request, string $governorate): JsonResponse
    {
        $model = Governorate::where('slug', $governorate)->firstOrFail();

        $this->assertOwns($model);

        try {
            $result = $this->backfiller->fix($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
