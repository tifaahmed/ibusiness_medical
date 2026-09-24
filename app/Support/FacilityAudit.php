<?php

namespace App\Support;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityBranchLog;
use App\Models\FacilityLog;
use App\Models\FacilityManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The audit trail for the writers that have no hand-written log call: the
 * spreadsheet imports, the migration package, the AI SEO and translation tools.
 *
 * The admin forms log for themselves, with their own snapshots. Everything
 * else runs inside `FacilityAudit::as('import', fn () => …)`, which names the
 * tool; while a source is set, FacilityAuditObserver files one entry per
 * created/updated facility, branch or manager, tagged with that source. With no
 * source set the observer does nothing — which is what keeps a form save from
 * being logged twice.
 */
class FacilityAudit
{
    public const SOURCE_IMPORT = 'import';

    public const SOURCE_MIGRATION = 'migration';

    public const SOURCE_AI_SEO = 'ai_seo';

    public const SOURCE_AI_TRANSLATE = 'ai_translate';

    private const FIELDS = [
        Facility::class => [
            'name', 'description', 'meta_title', 'meta_description', 'meta_keywords',
            'canonical_url', 'facility_type_id', 'sales_id', 'discount_percent', 'banner_config',
        ],
        FacilityBranch::class => [
            'id:branch_id', 'facility_id', 'name', 'address', 'phone', 'governorate_id', 'city_id',
            'latitude', 'longitude', 'google_location_url',
        ],
        FacilityManager::class => [
            'id:manager_id', 'facility_id', 'name', 'position', 'phones',
        ],
    ];

    private const JSON = [
        'name', 'description', 'meta_title', 'meta_description', 'meta_keywords',
        'banner_config', 'address', 'phone', 'phones',
    ];

    private const NUMERIC = [
        'facility_type_id', 'sales_id', 'discount_percent', 'facility_id', 'branch_id', 'manager_id',
        'governorate_id', 'city_id', 'latitude', 'longitude',
    ];

    private const DECIMALS = ['latitude', 'longitude', 'discount_percent'];

    private static ?string $source = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function as(string $source, Closure $callback): mixed
    {
        $previous = self::$source;
        self::$source = $source;

        try {
            return $callback();
        } finally {
            self::$source = $previous;
        }
    }

    public static function source(): ?string
    {
        return self::$source;
    }

    /**
     * A model's loggable fields in one comparable shape: JSON columns decoded,
     * numbers as numbers, blanks as null. Read from raw attributes so a cast or
     * accessor cannot make the same stored value look different before/after.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function snapshot(Model $model, array $attributes): array
    {
        $out = [];

        foreach (self::FIELDS[$model::class] ?? [] as $spec) {
            [$column, $key] = str_contains($spec, ':') ? explode(':', $spec) : [$spec, $spec];
            $value = $attributes[$column] ?? null;

            if (in_array($key, self::JSON, true) && is_string($value)) {
                $decoded = json_decode($value, true);
                $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }

            if (is_array($value)) {
                $value = array_filter($value, fn ($v) => $v !== null && $v !== '');
                $value = $value === [] ? null : $value;
            }

            if (in_array($key, self::NUMERIC, true) && is_numeric($value)) {
                $value = in_array($key, self::DECIMALS, true) ? (float) $value : (int) $value;
            }

            $out[$key] = $value === '' ? null : $value;
        }

        return $out;
    }

    /**
     * File the entry for a model that was just created (`$before` null) or
     * updated. Nothing is written when an update changed none of the logged
     * fields.
     *
     * @param  array<string, mixed>|null  $before  a snapshot(), or null for a create
     */
    public static function record(Model $model, ?array $before, ?string $source = null): void
    {
        $source ??= self::$source;
        $after = self::snapshot($model, $model->getAttributes());

        if ($before !== null && $before === $after) {
            return;
        }

        try {
            $request = request();
            $adminId = Auth::id();
            $facilityId = $model instanceof Facility ? $model->id : $model->facility_id;
            $created = $before === null;

            if ($model instanceof Facility) {
                FacilityLog::record($facilityId, $adminId, $created ? FacilityLog::ACTION_CREATED : FacilityLog::ACTION_UPDATED, $before, $after, $request, $source);
            } elseif ($model instanceof FacilityBranch) {
                FacilityBranchLog::record($model->id, $facilityId, $adminId, $created ? FacilityBranchLog::ACTION_CREATED : FacilityBranchLog::ACTION_UPDATED, $before, $after, $request, $source);
                FacilityLog::record($facilityId, $adminId, $created ? FacilityLog::ACTION_BRANCH_CREATED : FacilityLog::ACTION_BRANCH_UPDATED, $before, $after, $request, $source);
            } elseif ($model instanceof FacilityManager) {
                FacilityLog::record($facilityId, $adminId, $created ? FacilityLog::ACTION_MANAGER_CREATED : FacilityLog::ACTION_MANAGER_UPDATED, $before, $after, $request, $source);
            }
        } catch (\Throwable $e) {
            // A failed log must never cost the admin the write it describes.
            Log::warning('Facility audit entry could not be written.', [
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'source' => $source,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
