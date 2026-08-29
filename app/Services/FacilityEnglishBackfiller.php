<?php

namespace App\Services;

use App\Models\Facility;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fills / repairs the English (`en`) translation of a facility and its branches
 * with Gemini, using the Arabic value as the source of truth.
 *
 * Only `en` values are touched. A value is treated as needing a fix when the
 * Arabic side has content and the English side is blank, still holds Arabic
 * characters (pasted into the wrong input), or is a verbatim copy of the Arabic.
 *
 * The result is applied immediately — there is no preview step.
 */
class FacilityEnglishBackfiller
{
    /** Facility fields that carry prose worth translating. */
    private const FACILITY_FIELDS = ['name', 'description'];

    /** Branch fields. */
    private const BRANCH_FIELDS = ['name', 'address'];

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * Does this facility (or any of its branches) have an English field to fix?
     */
    public function hasWork(Facility $facility): bool
    {
        return $this->pending($facility) !== [];
    }

    /**
     * Generate and save corrected English for every pending field.
     *
     * @return array{applied: list<array{model: string, id: int, field: string, from: string, to: string}>, errors: list<string>}
     */
    public function fix(Facility $facility): array
    {
        $pending = $this->pending($facility);

        if ($pending === []) {
            return ['applied' => [], 'errors' => []];
        }

        $answers = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($facility, $pending),
            2048,
        );

        $applied = [];
        $errors = [];

        DB::transaction(function () use ($facility, $pending, $answers, &$applied, &$errors) {
            $branches = $facility->branches->keyBy('id');

            /** @var array<int, \Illuminate\Database\Eloquent\Model> $touched */
            $touched = [];

            foreach ($pending as $index => $row) {
                $value = data_get($answers, (string) $index);
                $value = is_scalar($value) ? trim((string) $value) : '';

                if ($value === '' || $this->looksArabic($value)) {
                    $errors[] = "Could not produce English for {$row['model']} #{$row['id']} {$row['field']}.";

                    continue;
                }

                $model = $row['model'] === 'facility'
                    ? $facility
                    : $branches->get($row['id']);

                if ($model === null) {
                    continue;
                }

                $from = (string) ($model->getTranslation($row['field'], 'en') ?? '');
                $model->setTranslation($row['field'], 'en', $value);

                $key = spl_object_id($model);
                $touched[$key] ??= $model;

                $applied[] = [
                    'model' => $row['model'],
                    'id' => $row['id'],
                    'field' => $row['field'],
                    'from' => $from,
                    'to' => $value,
                ];
            }

            // One save per model. The slug is regenerated on update by
            // spatie/laravel-sluggable and its source (the Arabic `name`) is
            // untouched, but Str::slug() of Arabic can collapse to an empty
            // string and pick up a "-1" suffix — so pin the slug back.
            foreach ($touched as $model) {
                $originalSlug = $model->getOriginal('slug');
                $model->save();

                if ($model->slug !== $originalSlug && filled($originalSlug)) {
                    $model->slug = $originalSlug;
                    $model->saveQuietly();
                }
            }
        });

        if ($applied !== []) {
            Log::info('Facility English backfill applied', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
                'fields' => array_map(fn ($a) => "{$a['model']}#{$a['id']}.{$a['field']}", $applied),
            ]);
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Every field on the facility + branches that needs an English fix.
     *
     * @return list<array{model: string, id: int, field: string, ar: string, context?: string}>
     */
    private function pending(Facility $facility): array
    {
        $rows = [];

        foreach (self::FACILITY_FIELDS as $field) {
            $ar = (string) ($facility->getTranslation($field, 'ar') ?? '');
            $en = (string) ($facility->getTranslation($field, 'en') ?? '');

            if ($this->needsFix($en, $ar)) {
                $rows[] = ['model' => 'facility', 'id' => $facility->id, 'field' => $field, 'ar' => $ar];
            }
        }

        foreach ($facility->branches as $branch) {
            foreach (self::BRANCH_FIELDS as $field) {
                $ar = (string) ($branch->getTranslation($field, 'ar') ?? '');
                $en = (string) ($branch->getTranslation($field, 'en') ?? '');

                if ($this->needsFix($en, $ar)) {
                    $place = collect([
                        $branch->city?->getTranslation('name', 'en') ?: $branch->city?->getTranslation('name', 'ar'),
                        $branch->governorate?->getTranslation('name', 'en') ?: $branch->governorate?->getTranslation('name', 'ar'),
                    ])->filter()->implode(', ');

                    $rows[] = [
                        'model' => 'branch',
                        'id' => $branch->id,
                        'field' => $field,
                        'ar' => $ar,
                        'context' => $place ?: '',
                    ];
                }
            }
        }

        return $rows;
    }

    public function needsFix(?string $en, ?string $ar): bool
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        if ($ar === '') {
            return false;
        }

        return $en === '' || $this->looksArabic($en) || $en === $ar;
    }

    private function looksArabic(string $value): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $value);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You localise Arabic data for an Egyptian medical directory into English.

        You are given a numbered list of fields, each with its Arabic value and the
        kind of field it is. Return ONLY a JSON object mapping each number (as a
        string key) to the corrected English string. No other keys, no commentary.

        Rules by field type:
        - facility name / branch name: render proper nouns by their common English
          spelling or Egyptian-Arabic transliteration, and translate the descriptor
          (مستشفى → "Hospital", عيادة → "Clinic", مركز → "Center", معمل → "Lab",
          صيدلية → "Pharmacy", د. → "Dr."). Title Case. No trailing punctuation.
        - address: a natural English street address. Transliterate street and area
          names, translate generic words (شارع → "Street", ميدان → "Square",
          برج → "Tower", الدور → "Floor"). Keep numbers as digits.
        - description: fluent, faithful English prose. Keep the meaning and tone;
          do not add facts.

        Never leave a value in Arabic. Never invent information that is not in the
        Arabic source.
        PROMPT;
    }

    /**
     * @param  list<array{model: string, id: int, field: string, ar: string, context?: string}>  $pending
     */
    private function userPrompt(Facility $facility, array $pending): string
    {
        $lines = [];
        $lines[] = 'Facility (for context): '
            .($facility->getTranslation('name', 'en')
                ?: $facility->getTranslation('name', 'ar')
                ?: '—');
        $lines[] = 'Facility type: '
            .($facility->facilityType?->getTranslation('name', 'en')
                ?: $facility->facilityType?->getTranslation('name', 'ar')
                ?: '—');
        $lines[] = '';
        $lines[] = 'Fields to translate:';

        foreach ($pending as $index => $row) {
            $kind = $row['model'] === 'facility'
                ? "facility {$row['field']}"
                : "branch {$row['field']}";
            $place = ! empty($row['context']) ? " (location: {$row['context']})" : '';
            $lines[] = "{$index}. [{$kind}]{$place} Arabic: {$row['ar']}";
        }

        return implode("\n", $lines);
    }
}
