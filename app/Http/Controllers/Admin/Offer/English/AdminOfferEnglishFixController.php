<?php

namespace App\Http\Controllers\Admin\Offer\English;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Offer;
use App\Services\OfferEnglishBackfiller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Backs the "Fix English fields with AI" button on the admin offer form.
 * Called over axios; fills or repairs the English title, short description and
 * full description of this one offer from its Arabic values, then answers JSON.
 */
class AdminOfferEnglishFixController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_OFFERS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_OFFERS;
    }

    public function __construct(private readonly OfferEnglishBackfiller $backfiller) {}

    public function __invoke(Request $request, string $offer): JsonResponse
    {
        $model = Offer::where('slug', $offer)->firstOrFail();

        $this->assertOwns($model);

        try {
            $result = $this->backfiller->fix($model);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
