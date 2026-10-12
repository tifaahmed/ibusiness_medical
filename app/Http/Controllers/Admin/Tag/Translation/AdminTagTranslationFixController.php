<?php

namespace App\Http\Controllers\Admin\Tag\Translation;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Tag;
use App\Services\TagTranslationFixer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * "Fix translations with AI" on the tag list.
 *
 * `preview` asks the AI for corrected Arabic + English names of every tag that
 * needs one and writes nothing; `apply` saves only the proposals the admin
 * ticked, re-checking each one, since it comes back from the browser.
 * An admin limited to their own tags only ever sees and fixes those.
 */
class AdminTagTranslationFixController extends BaseController
{
    use CreatorScoped;

    public function __construct(private readonly TagTranslationFixer $fixer) {}

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_TAGS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_TAGS;
    }

    public function preview(Request $request): JsonResponse
    {
        $tags = $this->applyCreatorScope(Tag::query())->orderBy('id')->get();

        try {
            $proposals = $this->fixer->propose($tags);
        } catch (RuntimeException $e) {
            Log::warning('Tag translation preview failed', [
                'route' => $request->route()?->getName(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $needing = $tags->filter(fn (Tag $tag) => $this->fixer->needsFix(
            $tag->getTranslation('name', 'ar', false),
            $tag->getTranslation('name', 'en', false),
        ))->count();

        return response()->json([
            'proposals' => $proposals,
            // Tags that need a fix but got no usable answer this time.
            'unresolved' => max(0, $needing - count($proposals)),
        ]);
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
        $tags = $this->applyCreatorScope(Tag::query())->whereKey($items->keys())->get();

        $updated = [];
        $skipped = [];

        try {
            DB::transaction(function () use ($tags, $items, &$updated, &$skipped) {
                foreach ($tags as $tag) {
                    $item = $items[$tag->id];
                    $ar = trim($item['ar']);
                    $en = trim($item['en']);

                    if (! $this->fixer->isArabicName($ar) || ! $this->fixer->isEnglishName($en)) {
                        $skipped[] = $tag->id;

                        continue;
                    }

                    $before = $tag->getTranslations('name');
                    $tag->setTranslation('name', 'ar', $ar);
                    $tag->setTranslation('name', 'en', $en);
                    $tag->save();

                    $updated[] = ['id' => $tag->id, 'from' => $before, 'to' => ['ar' => $ar, 'en' => $en]];
                }
            });
        } catch (Throwable $e) {
            Log::error('Tag translation apply failed', [
                'route' => $request->route()?->getName(),
                'ids' => $items->keys()->all(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Could not save the translations. Nothing was changed.'], 500);
        }

        Log::info('Tag translations fixed with AI', [
            'admin_id' => Auth::id(),
            'changes' => $updated,
            'skipped' => $skipped,
        ]);

        return response()->json([
            'updated' => count($updated),
            'skipped' => count($skipped) + $items->count() - $tags->count(),
        ]);
    }
}
