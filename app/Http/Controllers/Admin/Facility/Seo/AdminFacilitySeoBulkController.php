<?php

namespace App\Http\Controllers\Admin\Facility\Seo;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Facility;
use App\Services\Ai\RateLimitException;
use App\Services\FacilitySeoGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The "Fill SEO with AI" sweep on the facility list.
 *
 * The browser drives it the same way the product SEO sweep and the facility
 * English sweep are driven: call {@see begin()} once to get the work list, then
 * {@see step()} repeatedly with a small slice of slugs until the list is done.
 * Each step is a short request so nothing has to survive a long-running
 * connection on shared hosting.
 */
class AdminFacilitySeoBulkController extends BaseController
{
    use CreatorScoped;

    /** Facilities handled per step — each one is an AI round trip. */
    private const CHUNK = 3;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITIES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITIES;
    }

    public function __construct(private readonly FacilitySeoGenerator $generator) {}

    /**
     * Work list: which facilities need SEO copy, which need a share image.
     */
    public function begin(Request $request): JsonResponse
    {
        $mode = $request->input('mode') === 'all' ? 'all' : 'missing';

        $facilities = Facility::query()
            ->with('media')
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->get();

        $seoSlugs = $facilities
            ->filter(fn (Facility $f) => $mode === 'all' || $this->needsSeo($f))
            ->pluck('slug')
            ->values();

        $ogSlugs = $facilities
            ->filter(fn (Facility $f) => $this->needsOgImage($f))
            ->pluck('slug')
            ->values();

        return response()->json([
            'chunk' => self::CHUNK,
            'seo_slugs' => $seoSlugs,
            'og_slugs' => $ogSlugs,
            'total' => $seoSlugs->merge($ogSlugs)->unique()->count(),
        ]);
    }

    /**
     * Process one slice. `slugs` is at most CHUNK long; `do_seo` / `do_og` say
     * which jobs to run for each facility in the slice.
     */
    public function step(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'max:'.self::CHUNK],
            'slugs.*' => ['string'],
            'do_seo' => ['nullable', 'boolean'],
            'do_og' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:missing,all'],
        ]);

        $doSeo = filter_var($validated['do_seo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $doOg = filter_var($validated['do_og'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $overwrite = ($validated['mode'] ?? 'missing') === 'all';

        $facilities = Facility::query()
            ->with(['media', 'facilityType', 'governorate', 'city', 'branches.governorate', 'branches.city'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->whereIn('slug', $validated['slugs'])
            ->get();

        $results = [];
        $rateLimited = false;
        $first = true;

        foreach ($facilities as $facility) {
            if (! $first) {
                // One second between every AI call to ease provider rate limits.
                sleep(1);
            }
            $first = false;

            $result = ['slug' => $facility->slug, 'seo' => 'skip', 'og' => 'skip'];

            if ($doSeo && ($overwrite || $this->needsSeo($facility))) {
                try {
                    $this->fillSeo($facility, $overwrite);
                    $result['seo'] = 'ok';
                } catch (RateLimitException $e) {
                    $rateLimited = true;
                    break;
                } catch (RuntimeException $e) {
                    $result['seo'] = 'error';
                    $result['message'] = $e->getMessage();
                } catch (Throwable $e) {
                    Log::error('Bulk facility SEO failed', ['slug' => $facility->slug, 'error' => $e->getMessage()]);
                    $result['seo'] = 'error';
                    $result['message'] = 'Unexpected error — see the log.';
                }
            }

            if ($doOg && $this->needsOgImage($facility)) {
                try {
                    $this->copyCoverImageToOg($facility);
                    $result['og'] = 'ok';
                } catch (Throwable $e) {
                    Log::error('Bulk facility OG image copy failed', ['slug' => $facility->slug, 'error' => $e->getMessage()]);
                    $result['og'] = 'error';
                }
            }

            $results[] = $result;
        }

        return response()->json(['results' => $results, 'rate_limited' => $rateLimited]);
    }

    /**
     * True when neither meta_title nor meta_description has any locale filled.
     */
    private function needsSeo(Facility $facility): bool
    {
        return $this->allBlank($facility->getTranslations('meta_title'))
            || $this->allBlank($facility->getTranslations('meta_description'));
    }

    private function needsOgImage(Facility $facility): bool
    {
        return $facility->getFirstMedia('og_image') === null
            && $facility->getFirstMedia('image') !== null;
    }

    /**
     * @param  array<string, string|null>  $translations
     */
    private function allBlank(array $translations): bool
    {
        foreach ($translations as $value) {
            if (filled($value)) {
                return false;
            }
        }

        return true;
    }

    private function fillSeo(Facility $facility, bool $overwrite): void
    {
        $seo = $this->generator->generate([
            'name' => $facility->getTranslations('name'),
            'description' => $facility->getTranslations('description'),
            'facility_type' => $facility->facilityType?->getTranslation('name', app()->getLocale())
                ?: $facility->facilityType?->getTranslation('name', 'en'),
            'discount_percent' => $facility->discount_percent,
            'governorates' => $this->placeNames($facility, 'governorate'),
            'cities' => $this->placeNames($facility, 'city'),
        ]);

        foreach (['meta_title', 'meta_description', 'meta_keywords'] as $field) {
            $current = $facility->getTranslations($field);

            foreach (['ar', 'en'] as $locale) {
                $new = $seo[$field][$locale] ?? '';

                if ($new === '') {
                    continue;
                }

                if ($overwrite || blank($current[$locale] ?? null)) {
                    $facility->setTranslation($field, $locale, $new);
                }
            }
        }

        // Saving a facility regenerates its slug from the (Arabic) name, which
        // Str::slug() can collapse to "" + a "-1" suffix. The SEO fields never
        // touch the name, so pin the original slug back if that happened.
        $originalSlug = $facility->getOriginal('slug');
        $facility->save();

        if ($facility->slug !== $originalSlug && filled($originalSlug)) {
            $facility->slug = $originalSlug;
            $facility->saveQuietly();
        }
    }

    /**
     * Governorate / city names covered by this facility and its branches,
     * in the facility's own locale, deduplicated. Mirrors the per-facility
     * SEO card, which resolves branch place ids to names before sending.
     *
     * @return list<string>
     */
    private function placeNames(Facility $facility, string $relation): array
    {
        $names = collect([$facility->{$relation}])
            ->merge($facility->branches->map(fn ($branch) => $branch->{$relation}))
            ->filter()
            ->map(fn ($place) => $place->getTranslation('name', app()->getLocale())
                ?: $place->getTranslation('name', 'ar')
                ?: $place->getTranslation('name', 'en'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $names;
    }

    private function copyCoverImageToOg(Facility $facility): void
    {
        $cover = $facility->getFirstMedia('image');

        if ($cover === null) {
            return;
        }

        $facility->copyMedia($cover->getPath())->toMediaCollection('og_image');
    }
}
