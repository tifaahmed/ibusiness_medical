<?php

namespace App\Http\Controllers\Admin\FacilityBranch\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\FacilityBranch\List\AdminFacilityBranchListCollection;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Governorate;
use App\Services\BranchGeocoder;
use App\Services\BranchPlaceResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFacilityBranchListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FACILITY_BRANCHES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FACILITY_BRANCHES;
    }

    /**
     * Display a listing of facility branches.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $facilityBranches = FacilityBranch::with(['facility.facilityType', 'governorate', 'city', 'creator:id,name,email'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('name->'.app()->getLocale(), 'like', '%'.$filters['search'].'%')
                        ->orWhere('slug', 'like', '%'.$filters['search'].'%')
                        ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('facility', function ($q) use ($filters) {
                            $q->where('name->'.app()->getLocale(), 'like', '%'.$filters['search'].'%');
                        });
                });
            })
            ->when(! empty($filters['facility_id']), function ($q) use ($filters) {
                $q->where('facility_id', $filters['facility_id']);
            })
            ->when(! empty($filters['governorate_id']), fn ($q) => $q->where('governorate_id', $filters['governorate_id']))
            ->when(! empty($filters['city_id']), fn ($q) => $q->where('city_id', $filters['city_id']))
            ->when(! empty($filters['facility_type_id']), function ($q) use ($filters) {
                $q->whereHas('facility', fn ($q2) => $q2->where('facility_type_id', $filters['facility_type_id']));
            })
            // The rows nobody can place on a map, and the ones a migration
            // package cannot be imported over until somebody fills them in.
            ->when($filters['no_governorate'], fn ($q) => $q->whereNull('governorate_id'))
            ->when($filters['no_city'], fn ($q) => $q->whereNull('city_id'))
            ->when($filters['no_address'], fn ($q) => $q->tap(self::missingAddress(...)))
            ->latest()
            ->paginate($request->input('per_page', 15))->withQueryString();

        $facilities = Facility::all()->map(function ($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
            ];
        });

        $governorates = Governorate::orderBy('id')->get()->map(fn ($governorate) => [
            'id' => $governorate->id,
            'name' => $governorate->name,
        ]);

        $cities = City::orderBy('id')->get()->map(fn ($city) => [
            'id' => $city->id,
            'governorate_id' => $city->governorate_id,
            'name' => $city->name,
        ]);

        $facilityTypes = FacilityType::orderBy('id')->get()->map(fn ($facilityType) => [
            'id' => $facilityType->id,
            'name' => $facilityType->name,
        ]);

        // What each "missing" filter would find, counted over everything the
        // reader is allowed to see rather than the page in front of them — the
        // number is the size of the job, not of this screen.
        $incomplete = FacilityBranch::query()
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->selectRaw(
                'SUM(governorate_id IS NULL) AS no_governorate,'
                .' SUM(city_id IS NULL) AS no_city,'
                .' SUM('.self::MISSING_ADDRESS_SQL.') AS no_address,'
                // What each AI sweep would actually queue: a row it can help
                // with is one that has an address to read AND is missing the
                // thing being filled. Without the address there is nothing to
                // read, so it is not part of the job.
                .' SUM(NOT '.self::MISSING_ADDRESS_SQL.' AND (governorate_id IS NULL OR city_id IS NULL)) AS no_place,'
                .' SUM(NOT '.self::MISSING_ADDRESS_SQL.' AND (latitude IS NULL OR longitude IS NULL OR google_location_url IS NULL OR google_location_url = \'\')) AS no_location'
            )
            ->first();

        return Inertia::render('Admin/FacilityBranch/List', [
            'facilityBranches' => new AdminFacilityBranchListCollection($facilityBranches)->toArray($request),
            'filters' => $filters,
            'facilities' => $facilities,
            'governorates' => $governorates,
            'cities' => $cities,
            'facilityTypes' => $facilityTypes,
            'incompleteCounts' => [
                'no_governorate' => (int) ($incomplete->no_governorate ?? 0),
                'no_city' => (int) ($incomplete->no_city ?? 0),
                'no_address' => (int) ($incomplete->no_address ?? 0),
                'no_place' => (int) ($incomplete->no_place ?? 0),
                'no_location' => (int) ($incomplete->no_location ?? 0),
                'duplicate_names' => $this->duplicateNameCount(),
            ],
            // False when GEMINI_API_KEY is unset: the sweep buttons are hidden
            // rather than offered and then refused by the routes behind them.
            'placeAiEnabled' => BranchPlaceResolver::isConfigured(),
            'locationAiEnabled' => BranchGeocoder::isConfigured(),
        ]);
    }

    /**
     * Get filters from request.
     */
    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'facility_id' => $request->input('facility_id'),
            'governorate_id' => $request->input('governorate_id'),
            'city_id' => $request->input('city_id'),
            'facility_type_id' => $request->input('facility_type_id'),
            'no_governorate' => $request->boolean('no_governorate'),
            'no_city' => $request->boolean('no_city'),
            'no_address' => $request->boolean('no_address'),
        ];
    }

    /**
     * A branch whose address is not usable: the column is empty, or one of the
     * two languages was never filled in. Both are the same job to an admin —
     * the branch cannot be saved again until the missing side is typed — so the
     * filter and the count treat them as one.
     *
     * JSON_EXTRACT answers NULL for a key that is not there, and the literal
     * 'null' once unquoted for one stored as JSON null; both read as missing,
     * as does a value that is only whitespace. TRIM is written out rather than
     * left to MySQL's space-padded comparison, so the rule is the one stated
     * here and not a property of the column's collation.
     */
    private const MISSING_ADDRESS_SQL = <<<'SQL'
        (
            address IS NULL
            OR TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(address, '$.ar')), '')) IN ('', 'null')
            OR TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(address, '$.en')), '')) IN ('', 'null')
        )
        SQL;

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FacilityBranch>  $query
     */
    private static function missingAddress($query): void
    {
        $query->whereRaw(self::MISSING_ADDRESS_SQL);
    }

    /**
     * How many branches share a name with another branch of the same facility —
     * the backlog the "Fix branch names" sweep clears.
     *
     * These rows cannot be saved from the form at all until they are renamed:
     * App\Support\BranchUniqueness refuses them. Compared the way that class
     * compares — trimmed, whitespace collapsed, case folded, per language — so
     * the number on the button is the number the validator would object to.
     */
    private function duplicateNameCount(): int
    {
        $clash = fn (string $locale) => sprintf(
            "TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(facility_branches.name, '$.%s')), '')) <> ''"
            ." AND LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(facility_branches.name, '$.%s'))))"
            ." = LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(twin.name, '$.%s'))))",
            $locale,
            $locale,
            $locale
        );

        return (int) FacilityBranch::query()
            ->join('facility_branches AS twin', function ($join) {
                $join->on('twin.facility_id', '=', 'facility_branches.facility_id')
                    ->whereColumn('twin.id', '!=', 'facility_branches.id');
            })
            ->tap(fn ($q) => $this->applyCreatorScope($q, 'facility_branches.created_by'))
            ->whereRaw('(('.$clash('ar').') OR ('.$clash('en').'))')
            ->distinct()
            ->count('facility_branches.id');
    }
}
