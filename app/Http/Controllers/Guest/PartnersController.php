<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Contract;
use App\Http\Resources\Guest\FacilityCollection;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Models\Offer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnersController extends BaseController
{
    /**
     * Display the Partners page.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        // Fetch facilities with related data and pagination
        $facilities = Facility::with(['facilityType', 'branches.governorate', 'branches.city', 'media'])
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $locale     = app()->getLocale();
                $normalized = $this->normalizeSearch($filters['search']);

                $words = collect(preg_split('/\s+/', $normalized))
                    ->map(fn($w) => trim($w))
                    ->filter(fn($w) => mb_strlen($w) > 1)
                    ->values();

                if ($words->isEmpty()) {
                    $words = collect([$normalized]);
                }

                // Normalize Arabic variants directly in SQL so stored data is also normalized
                $path     = '$.' . $locale;
                $nameExpr = "replace(replace(replace(replace(" .
                            "json_unquote(json_extract(`name`, '{$path}')), " .
                            "'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";

                foreach ($words as $word) {
                    $q->where(function ($query) use ($word, $nameExpr) {
                        $query->whereRaw("{$nameExpr} like ?", ['%' . $word . '%'])
                              ->orWhere('slug', 'like', '%' . $word . '%');
                    });
                }
            })
            ->when(isset($filters['facility_type_id']) && $filters['facility_type_id'] !== '' && $filters['facility_type_id'] !== null, function ($q) use ($filters) {
                $q->where('facility_type_id', (int) $filters['facility_type_id']);
            })
            ->when(isset($filters['governorate_id']) && $filters['governorate_id'] !== '' && $filters['governorate_id'] !== null, function ($q) use ($filters) {
                $governorateId = (int) $filters['governorate_id'];
                $q->where(function ($query) use ($governorateId) {
                    $query->where('governorate_id', $governorateId)
                          ->orWhereHas('branches', fn($b) => $b->where('governorate_id', $governorateId));
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 12))->withQueryString();

        // Get filter options
        $governorates = Governorate::all()->map(function ($governorate) {
            return [
                'id' => $governorate->id,
                'name' => $governorate->name,
            ];
        });

        // Get facility types - filter by governorate if selected
        $facilityTypesQuery = FacilityType::query();
        
        if (isset($filters['governorate_id']) && $filters['governorate_id'] !== '' && $filters['governorate_id'] !== null) {
            // Get facility types that have facilities in the selected governorate
            $facilityTypeIds = Facility::where(function ($q) use ($filters) {
                    $governorateId = (int) $filters['governorate_id'];
                    $q->where('governorate_id', $governorateId)
                      ->orWhereHas('branches', fn($b) => $b->where('governorate_id', $governorateId));
                })->distinct()
                ->pluck('facility_type_id')
                ->filter()
                ->toArray();
            
            if (!empty($facilityTypeIds)) {
                $facilityTypesQuery->whereIn('id', $facilityTypeIds);
            } else {
                // If no facilities found, return empty result
                $facilityTypesQuery->whereRaw('1 = 0');
            }
        }
        
        $facilityTypes = $facilityTypesQuery->get()->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
            ];
        });

        // Fetch active offers with their relationships
        $offers = Offer::with(['offerable'])
            ->orderBy('created_at', 'desc')

            ->get()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'slug' => $offer->slug,
                    'short_description' => $offer->short_description,
                    'full_description' => $offer->full_description,
                    'phone' => $offer->phone,
                    'price' => $offer->price,
                    'old_price' => $offer->old_price,
                    'discount_percentage' => $offer->discount_percentage,
                    'has_discount' => $offer->hasDiscount(),
                    'image' => $offer->image ?: 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&h=400&fit=crop',
                    'thumbnail' => $offer->thumbnail ?: 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&h=400&fit=crop',
                    'offerable_type' => $offer->offerable_type,
                    'offerable_name' => $offer->offerable ? $offer->offerable->name : null,
                ];
            });

        $contracts = Contract::active()->ordered()->get()->map(function ($contract) {
            return [
                'id' => $contract->id,
                'name' => $contract->name,
                'description' => $contract->description,
                'phones' => $contract->phones,
                'slug' => $contract->slug,
                'image' => $contract->image,
            ];
        });

        $locale = app()->getLocale();
        $facilityNames = Facility::select('name', 'slug')->get()->map(function ($f) use ($locale) {
            return [
                'name' => $f->getTranslation('name', $locale),
                'slug' => $f->slug,
            ];
        })->values();

        return Inertia::render('Guest/PartnersPage', [
            'pageTitle' => 'Our Partners - ASH Health Care',
            'facilities' => new FacilityCollection($facilities)->toArray($request),
            'filters' => $filters,
            'facilityTypes' => $facilityTypes,
            'governorates' => $governorates,
            'offers' => $offers,
            'contracts' => $contracts,
            'facilityNames' => $facilityNames,
        ]);
    }

    /**
     * Normalize a search term for fuzzy Arabic matching.
     * - Lower-cases the term
     * - Collapses alef variants (أ إ آ ٱ) → ا
     * - Collapses ya variants (ى) → ي
     * - Strips Arabic tashkeel (diacritics)
     */
    protected function normalizeSearch(string $term): string
    {
        $term = mb_strtolower(trim($term));
        $term = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $term);
        $term = str_replace('ى', 'ي', $term);
        $term = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $term);
        return $term;
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'facility_type_id' => $request->input('facility_type_id'),
            'governorate_id' => $request->input('governorate_id'),
        ];
    }
}

