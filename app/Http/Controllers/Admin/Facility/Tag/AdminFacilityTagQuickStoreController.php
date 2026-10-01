<?php

namespace App\Http\Controllers\Admin\Facility\Tag;

use App\Http\Controllers\Admin\Tag\Actions\Store\StoreTagAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Tag\StoreTagRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The facility form's "Quick add tag" popup: creates the tag the same way the
 * tag page does (same request, same action) but answers JSON, so the new chip
 * lands in the open form — already ticked — without leaving the page.
 */
class AdminFacilityTagQuickStoreController extends BaseController
{
    public function __construct(private readonly StoreTagAction $storeAction) {}

    public function __invoke(StoreTagRequest $request): JsonResponse
    {
        try {
            $tag = $this->storeAction->execute($request->validated());
        } catch (Throwable $e) {
            Log::error('Quick tag create from the facility form failed', [
                'route' => $request->route()?->getName(),
                'name' => $request->input('name'),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to create tag. Please try again.'], 500);
        }

        return response()->json([
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'name_translations' => $tag->getTranslations('name'),
                'icon' => $tag->icon,
                'color' => $tag->color,
            ],
        ], 201);
    }
}
