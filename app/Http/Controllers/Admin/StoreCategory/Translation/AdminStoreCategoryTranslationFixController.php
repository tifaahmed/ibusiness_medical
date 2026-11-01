<?php

namespace App\Http\Controllers\Admin\StoreCategory\Translation;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\StoreCategory;
use App\Services\StoreCategoryTranslationFixer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * "Fix translations with AI" for store categories. `preview` (list) proposes fixes for every category that needs one
 * and writes nothing; `apply` saves only the ticked ones, re-checking each (it comes back from the browser); `single`
 * (create / edit form) fixes the two boxes as typed and writes nothing until the admin saves the form.
 */
class AdminStoreCategoryTranslationFixController extends BaseController
{
    use CreatorScoped;

    public function __construct(private readonly StoreCategoryTranslationFixer $fixer) {}

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_STORE_CATEGORIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_STORE_CATEGORIES;
    }

    public function preview(Request $request): JsonResponse
    {
        $categories = $this->applyCreatorScope(StoreCategory::query())->orderBy('id')->get();

        try {
            $proposals = $this->fixer->propose($categories);
        } catch (RuntimeException $e) {
            Log::warning('Store category translation preview failed', ['route' => $request->route()?->getName(), 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $needing = $categories->filter(fn (StoreCategory $c) => $this->fixer->needsFix(
            $c->getTranslation('name', 'ar', false),
            $c->getTranslation('name', 'en', false),
        ))->count();

        return response()->json(['proposals' => $proposals, 'unresolved' => max(0, $needing - count($proposals))]);
    }

    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.id' => ['required', 'integer'],
            'items.*.ar' => ['required', 'string', 'max:255'],
            'items.*.en' => ['required', 'string', 'max:255'],
        ]);

        $items = collect($validated['items'])->keyBy('id');
        $categories = $this->applyCreatorScope(StoreCategory::query())->whereKey($items->keys())->get();
        $updated = [];
        $skipped = [];

        try {
            DB::transaction(function () use ($categories, $items, &$updated, &$skipped) {
                foreach ($categories as $category) {
                    $ar = trim($items[$category->id]['ar']);
                    $en = trim($items[$category->id]['en']);

                    if (! $this->fixer->isArabicName($ar) || ! $this->fixer->isEnglishName($en)) {
                        $skipped[] = $category->id;

                        continue;
                    }

                    $before = $category->getTranslations('name');
                    $category->setTranslation('name', 'ar', $ar);
                    $category->setTranslation('name', 'en', $en);
                    $category->save();

                    $updated[] = ['id' => $category->id, 'from' => $before, 'to' => ['ar' => $ar, 'en' => $en]];
                }
            });
        } catch (Throwable $e) {
            Log::error('Store category translation apply failed', ['route' => $request->route()?->getName(), 'ids' => $items->keys()->all(), 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Could not save the translations. Nothing was changed.'], 500);
        }

        Log::info('Store category translations fixed with AI', ['admin_id' => Auth::id(), 'changes' => $updated, 'skipped' => $skipped]);

        return response()->json(['updated' => count($updated), 'skipped' => count($skipped) + $items->count() - $categories->count()]);
    }

    public function single(Request $request): JsonResponse
    {
        $data = $request->validate(['ar' => ['nullable', 'string', 'max:255'], 'en' => ['nullable', 'string', 'max:255']]);

        try {
            $pair = $this->fixer->fixPair($data['ar'] ?? null, $data['en'] ?? null);
        } catch (RuntimeException $e) {
            Log::warning('Store category single translation failed', ['route' => $request->route()?->getName(), 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($pair === null) {
            return response()->json(['message' => 'The AI gave no usable translation. Check the name and try again.'], 422);
        }

        return response()->json($pair);
    }
}
