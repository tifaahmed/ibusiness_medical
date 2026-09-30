<?php

namespace App\Console\Commands;

use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Services\Ai\RateLimitException;
use App\Services\BranchGeocoder;
use App\Support\GeoJson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Geocodes every branch that has no coordinates (Gemini, via BranchGeocoder) and
 * writes the point ONLY when it lies inside the border of the branch's own
 * governorate and the model is not merely guessing the city centre — a guess that lands in another governorate is a wrong pin, and an
 * empty location is better than a wrong one. Resumable: a branch that got a
 * point is no longer selected, so an interrupted run is simply started again.
 * Never touches a branch that already has coordinates. `--dry` writes nothing.
 */
class FillBranchGps extends Command
{
    protected $signature = 'branches:fill-gps
        {--limit=0 : Stop after this many branches (0 = all)}
        {--dry : Show what would be written and write nothing}';

    protected $description = 'Fill missing branch GPS with AI, keeping only points inside the branch\'s governorate';

    public function handle(BranchGeocoder $geocoder): int
    {
        if (! BranchGeocoder::isConfigured()) {
            $this->error('GEMINI_API_KEY is not set.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $limit = (int) $this->option('limit');

        $branches = FacilityBranch::query()
            ->with(['facility:id,name', 'governorate', 'city:id,name'])
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->whereNotNull('governorate_id')
            ->orderBy('id')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();

        $this->info($branches->count().' branches without GPS'.($dry ? ' (dry run)' : '').'.');

        $stats = ['saved' => 0, 'outside' => 0, 'not_found' => 0, 'low_confidence' => 0, 'no_border' => 0, 'error' => 0];
        $n = 0;

        foreach ($branches as $branch) {
            $n++;
            $result = $this->locate($geocoder, $branch);
            if ($result === null) {
                $this->error('Gave up after repeated failures; run the command again to resume.');

                return self::FAILURE;
            }

            $state = $this->handleResult($branch, $result, $dry);
            $stats[$state]++;

            if ($n % 25 === 0) {
                $this->info("… {$n} / {$branches->count()}  saved {$stats['saved']}, outside {$stats['outside']}, not found {$stats['not_found']}");
            }
            sleep(1);
        }

        Log::info('branches:fill-gps finished', ['dry' => $dry] + $stats);
        $this->info(sprintf(
            'Saved %d; outside the governorate (skipped) %d; not found %d; low confidence (skipped) %d; governorate has no border %d; errors %d.',
            $stats['saved'], $stats['outside'], $stats['not_found'], $stats['low_confidence'], $stats['no_border'], $stats['error']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null the geocoder's answer, ['error' => …] for a branch it choked on, null when it keeps hitting the rate limit
     */
    private function locate(BranchGeocoder $geocoder, FacilityBranch $branch): ?array
    {
        $context = [
            'facility_name' => $branch->facility?->getTranslations('name'),
            'name' => $branch->getTranslations('name'),
            'address' => $branch->getTranslations('address'),
            'governorate' => $this->placeName($branch->governorate),
            'city' => $this->placeName($branch->city),
        ];

        if (! BranchGeocoder::hasEnoughContext($context)) {
            return ['latitude' => null, 'longitude' => null, 'confidence' => 'low'];
        }

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            try {
                return $geocoder->locate($context);
            } catch (RateLimitException) {
                $this->warn("Rate limited, waiting 15s (attempt {$attempt})…");
                sleep(15);
            } catch (\Throwable $e) {
                Log::error('branches:fill-gps geocode failed', ['branch_id' => $branch->id, 'attempt' => $attempt, 'error' => $e->getMessage()]);
                if ($attempt >= 3) {
                    return ['error' => $e->getMessage()];
                }
                sleep(5);
            }
        }

        return null;
    }

    private function handleResult(FacilityBranch $branch, array $result, bool $dry): string
    {
        if (isset($result['error'])) {
            return 'error';
        }

        if ($result['latitude'] === null) {
            return 'not_found';
        }

        // "low" is the city or governorate centre — a guess, not the branch's place.
        if (($result['confidence'] ?? 'low') === 'low') {
            return 'low_confidence';
        }

        $border = $branch->governorate?->boundary;
        if (empty($border)) {
            return 'no_border';
        }

        if (! GeoJson::contains($border, (float) $result['longitude'], (float) $result['latitude'])) {
            $this->line(sprintf('#%d outside %s: %s, %s (%s)', $branch->id, $this->placeName($branch->governorate), $result['latitude'], $result['longitude'], $result['matched_place'] ?? '—'));

            return 'outside';
        }

        if ($dry) {
            $this->line(sprintf('#%d %s, %s [%s] %s', $branch->id, $result['latitude'], $result['longitude'], $result['confidence'], $result['matched_place'] ?? '—'));

            return 'saved';
        }

        try {
            $before = [
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'google_location_url' => $branch->google_location_url,
            ];

            $branch->latitude = $result['latitude'];
            $branch->longitude = $result['longitude'];
            // Built from the coordinates just written, so the link can never disagree with them.
            $branch->google_location_url = BranchGeocoder::mapsUrl($branch->latitude, $branch->longitude);
            // saveQuietly: a normal save regenerates the slug from the facility name.
            $branch->saveQuietly();

            FacilityBranchLog::record(
                facilityBranchId: $branch->id,
                facilityId: $branch->facility_id,
                adminId: null,
                action: FacilityBranchLog::ACTION_UPDATED,
                oldValues: $before,
                newValues: [
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'google_location_url' => $branch->google_location_url,
                    'source' => 'ai_geocode_cli',
                    'confidence' => $result['confidence'],
                ],
            );
        } catch (\Throwable $e) {
            Log::error('branches:fill-gps save failed', ['branch_id' => $branch->id, 'error' => $e->getMessage()]);

            return 'error';
        }

        return 'saved';
    }

    private function placeName(mixed $place): ?string
    {
        return $place?->getTranslation('name', 'en') ?: $place?->getTranslation('name', 'ar') ?: null;
    }
}
