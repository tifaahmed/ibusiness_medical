<?php

namespace App\Services;

use App\Models\FacilityType;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills / repairs the English (`en`) translation of a facility type's `name`
 * with Gemini, using the Arabic value as the source of truth.
 *
 * A value is treated as needing a fix when the Arabic side has content and the
 * English side is blank, still holds Arabic characters (pasted into the wrong
 * input), or is a verbatim copy of the Arabic. The result is applied
 * immediately — there is no preview step.
 */
class FacilityTypeEnglishBackfiller
{
    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * Does this facility type have an English name to fix?
     */
    public function hasWork(FacilityType $facilityType): bool
    {
        return $this->needsFix(
            (string) ($facilityType->getTranslation('name', 'en') ?? ''),
            (string) ($facilityType->getTranslation('name', 'ar') ?? ''),
        );
    }

    /**
     * Generate and save corrected English for the name, when it needs one.
     *
     * @return array{applied: list<array{field: string, from: string, to: string}>, errors: list<string>}
     */
    public function fix(FacilityType $facilityType): array
    {
        $ar = (string) ($facilityType->getTranslation('name', 'ar') ?? '');
        $en = (string) ($facilityType->getTranslation('name', 'en') ?? '');

        if (! $this->needsFix($en, $ar)) {
            return ['applied' => [], 'errors' => []];
        }

        $value = $this->translate($ar);

        if ($value === null) {
            return ['applied' => [], 'errors' => ['Could not produce English for the name.']];
        }

        $applied = [];

        DB::transaction(function () use ($facilityType, $value, &$applied) {
            $applied[] = ['field' => 'name', 'from' => $en, 'to' => $value];
            $facilityType->setTranslation('name', 'en', $value);

            // Saving regenerates the slug from the (Arabic) name, which
            // Str::slug() can collapse to "" + a "-1" suffix. The Arabic name
            // is untouched here, so pin the original slug back.
            $originalSlug = $facilityType->getOriginal('slug');
            $facilityType->save();

            if ($facilityType->slug !== $originalSlug && filled($originalSlug)) {
                $facilityType->slug = $originalSlug;
                $facilityType->saveQuietly();
            }
        });

        Log::info('Facility type English backfill applied', [
            'facility_type_id' => $facilityType->id,
            'facility_type_slug' => $facilityType->slug,
        ]);

        return ['applied' => $applied, 'errors' => []];
    }

    /**
     * English for an Arabic name typed into the create form, where there is no
     * saved row yet. Nothing is written; the caller puts the answer back into
     * the open form for the admin to check before saving.
     */
    public function translateName(string $ar): ?string
    {
        return $this->translate($ar);
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

    private function translate(string $ar): ?string
    {
        $ar = trim($ar);
        if ($ar === '') {
            return null;
        }

        $answers = $this->ai->json($this->systemPrompt(), "0. [facility type name] Arabic: {$ar}", 256);

        $value = data_get($answers, '0');
        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($value === '' || $this->looksArabic($value)) {
            return null;
        }

        return $value;
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

        facility type name: the common English name for this kind of medical
        facility (e.g. مستشفى → "Hospital", عيادة → "Clinic", مركز → "Center",
        معمل → "Lab", صيدلية → "Pharmacy"). Title Case. No trailing punctuation.

        Never leave a value in Arabic.
        PROMPT;
    }
}
