<?php

namespace App\Services;

use App\Models\Governorate;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fills / repairs the English (`en`) translation of a governorate's `name`
 * — and the names of its cities — with Gemini, using the Arabic value as the
 * source of truth for each.
 *
 * A value is treated as needing a fix when the Arabic side has content and the
 * English side is blank, still holds Arabic characters (pasted into the wrong
 * input), or is a verbatim copy of the Arabic. The result is applied
 * immediately — there is no preview step.
 */
class GovernorateEnglishBackfiller
{
    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * Does this governorate (or any of its cities) have an English name to fix?
     */
    public function hasWork(Governorate $governorate): bool
    {
        return $this->pending($governorate) !== [];
    }

    /**
     * Generate and save corrected English for the governorate name and every
     * pending city name.
     *
     * @return array{applied: list<array{model: string, id: int, field: string, from: string, to: string}>, errors: list<string>}
     */
    public function fix(Governorate $governorate): array
    {
        $pending = $this->pending($governorate);

        if ($pending === []) {
            return ['applied' => [], 'errors' => []];
        }

        $answers = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($governorate, $pending),
            512 + (count($pending) * 64),
        );

        $applied = [];
        $errors = [];

        DB::transaction(function () use ($governorate, $pending, $answers, &$applied, &$errors) {
            $cities = $governorate->cities->keyBy('id');

            /** @var array<int, \Illuminate\Database\Eloquent\Model> $touched */
            $touched = [];

            foreach ($pending as $index => $row) {
                $value = data_get($answers, (string) $index);
                $value = is_scalar($value) ? trim((string) $value) : '';

                if ($value === '' || $this->looksArabic($value)) {
                    $errors[] = "Could not produce English for {$row['model']} #{$row['id']} {$row['field']}.";

                    continue;
                }

                $model = $row['model'] === 'governorate'
                    ? $governorate
                    : $cities->get($row['id']);

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

            // One save per model. Saving regenerates the slug from the
            // (Arabic) name, which Str::slug() can collapse to "" + a "-1"
            // suffix. The Arabic name is untouched here, so pin the original
            // slug back.
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
            Log::info('Governorate English backfill applied', [
                'governorate_id' => $governorate->id,
                'governorate_slug' => $governorate->slug,
                'fields' => array_map(fn ($a) => "{$a['model']}#{$a['id']}.{$a['field']}", $applied),
            ]);
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * English for an Arabic name typed into the create form, where there is no
     * saved row yet. Nothing is written; the caller puts the answer back into
     * the open form for the admin to check before saving.
     */
    public function translateName(string $ar): ?string
    {
        $ar = trim($ar);
        if ($ar === '') {
            return null;
        }

        $answers = $this->ai->json($this->systemPrompt(), "0. [governorate name] Arabic: {$ar}", 256);

        $value = data_get($answers, '0');
        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($value === '' || $this->looksArabic($value)) {
            return null;
        }

        return $value;
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

    /**
     * Every field on the governorate + its cities that needs an English fix.
     *
     * @return list<array{model: string, id: int, field: string, ar: string}>
     */
    private function pending(Governorate $governorate): array
    {
        $rows = [];

        $ar = (string) ($governorate->getTranslation('name', 'ar') ?? '');
        $en = (string) ($governorate->getTranslation('name', 'en') ?? '');

        if ($this->needsFix($en, $ar)) {
            $rows[] = ['model' => 'governorate', 'id' => $governorate->id, 'field' => 'name', 'ar' => $ar];
        }

        foreach ($governorate->cities as $city) {
            $ar = (string) ($city->getTranslation('name', 'ar') ?? '');
            $en = (string) ($city->getTranslation('name', 'en') ?? '');

            if ($this->needsFix($en, $ar)) {
                $rows[] = ['model' => 'city', 'id' => $city->id, 'field' => 'name', 'ar' => $ar];
            }
        }

        return $rows;
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

        governorate name: the common English name of this Egyptian governorate
        (e.g. القاهرة → "Cairo", الجيزة → "Giza", الإسكندرية → "Alexandria").
        Title Case. No trailing punctuation.

        city name: the common English name of this Egyptian city or district,
        transliterated or translated the way it is normally written in English
        (e.g. المعادي → "Maadi", مدينة نصر → "Nasr City", الجيزة → "Giza").
        Title Case. No trailing punctuation.

        Never leave a value in Arabic.
        PROMPT;
    }

    /**
     * @param  list<array{model: string, id: int, field: string, ar: string}>  $pending
     */
    private function userPrompt(Governorate $governorate, array $pending): string
    {
        $lines = [];
        $lines[] = 'Governorate (for context): '
            .($governorate->getTranslation('name', 'en')
                ?: $governorate->getTranslation('name', 'ar')
                ?: '—');
        $lines[] = '';
        $lines[] = 'Fields to translate:';

        foreach ($pending as $index => $row) {
            $kind = $row['model'] === 'governorate' ? 'governorate name' : 'city name';
            $lines[] = "{$index}. [{$kind}] Arabic: {$row['ar']}";
        }

        return implode("\n", $lines);
    }
}
