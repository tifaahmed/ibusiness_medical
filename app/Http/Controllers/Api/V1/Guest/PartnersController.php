<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Guest\OfferResource;
use App\Http\Resources\Guest\FacilityCollection;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PartnersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'facility_type_id' => $request->input('facility_type_id'),
            'governorate_id' => $request->input('governorate_id'),
        ];

        $facilities = Facility::with(['facilityType', 'branches', 'media', 'tags'])
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $locale = app()->getLocale();
                $normalized = $this->normalizeSearch($filters['search']);

                $words = collect(preg_split('/\s+/', $normalized))
                    ->map(fn ($w) => trim($w))
                    ->filter(fn ($w) => mb_strlen($w) > 1)
                    ->values();

                if ($words->isEmpty()) {
                    $words = collect([$normalized]);
                }

                $path = '$.'.$locale;
                $nameExpr = 'replace(replace(replace(replace('.
                    "json_unquote(json_extract(`name`, '{$path}')), ".
                    "'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";

                foreach ($words as $word) {
                    $q->where(function ($query) use ($word, $nameExpr) {
                        $query->whereRaw("{$nameExpr} like ?", ['%'.$word.'%'])
                            ->orWhere('slug', 'like', '%'.$word.'%');
                    });
                }
            })
            ->when(! empty($filters['facility_type_id']), fn ($q) => $q->where('facility_type_id', (int) $filters['facility_type_id']))
            ->when(! empty($filters['governorate_id']), function ($q) use ($filters) {
                $governorateId = (int) $filters['governorate_id'];
                $q->where(function ($query) use ($governorateId) {
                    $query->where('governorate_id', $governorateId)
                        ->orWhereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId));
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 12))
            ->withQueryString();

        $governorates = Governorate::all()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
        ]);

        $facilityTypesQuery = FacilityType::query();
        if (! empty($filters['governorate_id'])) {
            $governorateId = (int) $filters['governorate_id'];
            $ids = Facility::where(function ($q) use ($governorateId) {
                $q->where('governorate_id', $governorateId)
                    ->orWhereHas('branches', fn ($b) => $b->where('governorate_id', $governorateId));
            })->distinct()->pluck('facility_type_id')->filter()->toArray();
            $facilityTypesQuery->when($ids, fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->whereRaw('1 = 0'));
        }

        $facilityTypes = $facilityTypesQuery->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
        ]);

        $locale = App::getLocale();
        $facilityNames = Facility::select('name', 'slug')->get()->map(fn ($f) => [
            'slug' => $f->slug,
            'name' => is_string($f->name) ? $f->name : ($f->getTranslation('name', $locale) ?? ''),
        ]);

        // The offers carousel sits above the grid on every consumer of this
        // endpoint, so it ships in the same response: one request paints the
        // whole page instead of the grid arriving before the banner.
        $offers = OfferResource::collection(
            Offer::with(['offerable'])->orderByDesc('created_at')->get()
        );

        return response()->json([
            'facilities' => (new FacilityCollection($facilities))->toArray($request),
            'filters' => $filters,
            'facility_types' => $facilityTypes,
            'governorates' => $governorates,
            'facility_names' => $facilityNames,
            'offers' => $offers,
        ]);
    }

    protected function normalizeSearch(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $term);
        $term = str_replace('ى', 'ي', $term);
        $term = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $term);

        return $term;
    }
}
