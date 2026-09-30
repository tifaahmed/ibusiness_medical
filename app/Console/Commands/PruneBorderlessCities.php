<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\Membership;
use App\Models\Service;
use App\Services\BranchGeocoder;
use App\Support\GeoJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

/**
 * A city with no `boundary` is one no border-drawing pass ever placed on the
 * map (the CitySeeder canonical markaz list ran on top of the older
 * neighbourhood-name cities, so a governorate can now hold more cities than
 * it has borders for). This deletes every borderless city, first re-pointing
 * anything that referenced it — `facility_branches`, `memberships`,
 * `addresses`, `facilities`, `services` — at the bordered city that actually
 * contains it.
 *
 * "Contains it" is decided by point-in-polygon against every bordered city of
 * the SAME governorate, smallest match wins on overlap (mirrors
 * AreaSeeder::refile()). A branch with coordinates is placed on its own; a
 * branch/membership/address with none takes the majority placement already
 * found for other rows of the same borderless city, or an AI geocode of the
 * branch's address (BranchGeocoder) when nothing else is available, or
 * finally the governorate's own capital city (always bordered) so nothing is
 * ever left pointing at a deleted row.
 *
 * Resumable and safe to re-run: only acts on cities with `boundary IS NULL`,
 * and a city still referenced by something it could not place is skipped
 * (reported), never force-deleted. `--dry` writes nothing.
 */
class PruneBorderlessCities extends Command
{
    protected $signature = 'cities:prune-borderless {--dry : Show what would change and write nothing}';

    protected $description = 'Delete cities with no border, re-pointing their branches/memberships/addresses at the bordered city that contains them';

    /** @var array<int, array<int, array{id:int, name:string, boundary:array, area:float}>> governorate_id => bordered cities */
    private array $borderedByGovernorate = [];

    public function handle(BranchGeocoder $geocoder): int
    {
        $dry = (bool) $this->option('dry');

        $cities = City::query()->whereNull('boundary')->orderBy('id')->get();
        $this->info($cities->count().' borderless cities found'.($dry ? ' (dry run)' : '').'.');

        $stats = ['deleted' => 0, 'kept_no_refs' => 0, 'skipped' => 0, 'branches_moved' => 0, 'memberships_moved' => 0, 'addresses_moved' => 0];

        foreach ($cities as $city) {
            $this->processCity($city, $geocoder, $dry, $stats);
        }

        $this->info(sprintf(
            'Done. Deleted %d, skipped %d (still stuck), branches moved %d, memberships moved %d, addresses moved %d.',
            $stats['deleted'], $stats['skipped'], $stats['branches_moved'], $stats['memberships_moved'], $stats['addresses_moved']
        ));

        return self::SUCCESS;
    }

    private function processCity(City $city, BranchGeocoder $geocoder, bool $dry, array &$stats): void
    {
        $label = $city->getTranslation('name', 'en', false) ?: $city->getTranslation('name', 'ar', false);

        $branches = FacilityBranch::where('city_id', $city->id)->get(['id', 'facility_id', 'governorate_id', 'city_id', 'latitude', 'longitude', 'name', 'address']);
        $memberships = Membership::where('city_id', $city->id)->get(['id', 'city_id']);
        $addresses = Address::where('city_id', $city->id)->get(['id', 'city_id']);
        $facilities = Facility::where('city_id', $city->id)->count();
        $services = Service::where('city_id', $city->id)->count();

        if ($branches->isEmpty() && $memberships->isEmpty() && $addresses->isEmpty() && $facilities === 0 && $services === 0) {
            $this->line("#{$city->id} {$label}: no references, deleting.");
            if (! $dry) {
                $city->delete();
            }
            $stats['deleted']++;

            return;
        }

        if ($facilities > 0 || $services > 0) {
            $this->warn("#{$city->id} {$label}: skipped — still referenced by facilities/services, which this command does not re-point.");
            $stats['skipped']++;

            return;
        }

        $bordered = $this->borderedCities((int) $city->governorate_id);
        if ($bordered === []) {
            $this->warn("#{$city->id} {$label}: skipped — its governorate has no bordered city to move anything to.");
            $stats['skipped']++;

            return;
        }

        // Resolve every branch that carries its own coordinates first.
        $resolutions = []; // branch id => target city id
        foreach ($branches as $branch) {
            if ($branch->latitude === null || $branch->longitude === null) {
                continue;
            }
            $target = $this->matchCity($bordered, (float) $branch->longitude, (float) $branch->latitude);
            if ($target !== null) {
                $resolutions[$branch->id] = $target;
            }
        }

        // A branch this city already had that we still can't place: try an AI
        // geocode of its written address before falling back to the capital.
        $fallback = $resolutions === [] ? $this->geocodeFallback($city, $branches, $bordered, $geocoder) : null;

        $majority = $resolutions === [] ? $fallback : $this->mode($resolutions);
        $majority ??= $this->capitalCity((int) $city->governorate_id, $bordered);

        if ($majority === null) {
            $this->warn("#{$city->id} {$label}: skipped — could not determine any target city.");
            $stats['skipped']++;

            return;
        }

        DB::transaction(function () use ($city, $label, $branches, $memberships, $addresses, $resolutions, $majority, $dry, &$stats, $bordered) {
            foreach ($branches as $branch) {
                $target = $resolutions[$branch->id] ?? $majority;
                if ($dry) {
                    $this->line(sprintf('  [from #%d %s] branch #%d -> city #%d (%s)', $city->id, $label, $branch->id, $target, $bordered[$target]['name']));
                    $stats['branches_moved']++;

                    continue;
                }

                $before = ['city_id' => $branch->city_id, 'area_id' => $branch->area_id];
                $branch->city_id = $target;
                // saveQuietly skips the booted() listener that drops a stale
                // area_id on a city change, so do it here instead: an area
                // only means something inside its own city.
                if (\Illuminate\Support\Facades\Schema::hasColumn('facility_branches', 'area_id')) {
                    $branch->area_id = null;
                }
                $branch->saveQuietly();

                FacilityBranchLog::record(
                    facilityBranchId: $branch->id,
                    facilityId: $branch->facility_id,
                    adminId: null,
                    action: FacilityBranchLog::ACTION_UPDATED,
                    oldValues: $before,
                    newValues: ['city_id' => $target, 'reason' => 'source city had no border (cities:prune-borderless)'],
                );
                $stats['branches_moved']++;
            }

            foreach ($memberships as $membership) {
                if (! $dry) {
                    Membership::where('id', $membership->id)->update(['city_id' => $majority]);
                }
                $stats['memberships_moved']++;
            }

            foreach ($addresses as $address) {
                if (! $dry) {
                    Address::where('id', $address->id)->update(['city_id' => $majority]);
                }
                $stats['addresses_moved']++;
            }

            if (! $dry) {
                Log::info('cities:prune-borderless moved city', [
                    'from_city_id' => $city->id,
                    'to_city_id' => $majority,
                    'branches' => $branches->count(),
                    'memberships' => $memberships->count(),
                    'addresses' => $addresses->count(),
                ]);
                $city->delete();
            }
            $stats['deleted']++;
        });

        $this->line("#{$city->id} {$label}: {$branches->count()} branch(es) re-pointed, deleted.");
    }

    /**
     * @return array<int, array{id:int, name:string, boundary:array, area:float}>
     */
    private function borderedCities(int $governorateId): array
    {
        if (isset($this->borderedByGovernorate[$governorateId])) {
            return $this->borderedByGovernorate[$governorateId];
        }

        $rows = City::where('governorate_id', $governorateId)->whereNotNull('boundary')->get(['id', 'name', 'boundary']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->id] = [
                'id' => $row->id,
                'name' => $row->getTranslation('name', 'en', false) ?: $row->getTranslation('name', 'ar', false),
                'boundary' => $row->boundary,
                'area' => $this->polygonArea($row->boundary),
            ];
        }

        return $this->borderedByGovernorate[$governorateId] = $out;
    }

    /**
     * Smallest bordered city (by our relative area proxy) whose polygon
     * contains the point, mirroring AreaSeeder's "smallest wins" rule.
     *
     * @param  array<int, array{id:int, name:string, boundary:array, area:float}>  $bordered
     */
    private function matchCity(array $bordered, float $lng, float $lat): ?int
    {
        $best = null;
        $bestArea = INF;
        foreach ($bordered as $candidate) {
            if (! GeoJson::contains($candidate['boundary'], $lng, $lat)) {
                continue;
            }
            if ($candidate['area'] < $bestArea) {
                $bestArea = $candidate['area'];
                $best = $candidate['id'];
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array{id:int, name:string, boundary:array, area:float}>  $bordered
     */
    private function capitalCity(int $governorateId, array $bordered): ?int
    {
        $governorate = \App\Models\Governorate::find($governorateId);
        if ($governorate === null) {
            return null;
        }
        $govName = $governorate->getTranslation('name', 'en', false);

        foreach ($bordered as $candidate) {
            if ($candidate['name'] === $govName) {
                return $candidate['id'];
            }
        }

        // No same-named capital: fall back to the biggest bordered city (by
        // area) in the governorate, on the assumption it is the main urban one.
        if ($bordered === []) {
            return null;
        }
        $biggest = null;
        $biggestArea = -1.0;
        foreach ($bordered as $candidate) {
            if ($candidate['area'] > $biggestArea) {
                $biggestArea = $candidate['area'];
                $biggest = $candidate['id'];
            }
        }

        return $biggest;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FacilityBranch>  $branches
     * @param  array<int, array{id:int, name:string, boundary:array, area:float}>  $bordered
     */
    private function geocodeFallback(City $city, $branches, array $bordered, BranchGeocoder $geocoder): ?int
    {
        if (! BranchGeocoder::isConfigured() || $branches->isEmpty()) {
            return null;
        }

        $governorate = $city->governorate;
        $branch = $branches->first();

        $context = [
            'facility_name' => $branch->facility?->getTranslations('name'),
            'name' => $branch->getTranslations('name'),
            'address' => $branch->getTranslations('address'),
            'governorate' => $governorate?->getTranslation('name', 'en', false),
            'city' => $city->getTranslation('name', 'en', false),
        ];

        if (! BranchGeocoder::hasEnoughContext($context)) {
            return null;
        }

        try {
            $result = $geocoder->locate($context);
        } catch (\Throwable $e) {
            Log::warning('cities:prune-borderless geocode fallback failed', ['city_id' => $city->id, 'error' => $e->getMessage()]);

            return null;
        }

        if (($result['latitude'] ?? null) === null || ($result['confidence'] ?? 'low') === 'low') {
            return null;
        }

        return $this->matchCity($bordered, (float) $result['longitude'], (float) $result['latitude']);
    }

    /**
     * @param  array<int, int>  $resolutions
     */
    private function mode(array $resolutions): ?int
    {
        if ($resolutions === []) {
            return null;
        }
        $counts = array_count_values($resolutions);
        arsort($counts);

        return (int) array_key_first($counts);
    }

    /**
     * Relative area of a GeoJSON Polygon/MultiPolygon in squared degrees —
     * good enough to rank overlapping city borders against each other; not a
     * real-world area.
     */
    private function polygonArea(?array $geometry): float
    {
        if (! $geometry || ! isset($geometry['type'], $geometry['coordinates'])) {
            return INF;
        }

        $polygons = match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };

        $total = 0.0;
        foreach ($polygons as $polygon) {
            $ring = $polygon[0] ?? [];
            $sum = 0.0;
            for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
                $sum += $ring[$i][0] * $ring[$i + 1][1] - $ring[$i + 1][0] * $ring[$i][1];
            }
            $total += abs($sum / 2);
        }

        return $total > 0 ? $total : INF;
    }
}
