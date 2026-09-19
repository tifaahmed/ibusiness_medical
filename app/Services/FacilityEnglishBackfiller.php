<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            $this->saveTouched($touched);
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
     * One save per model. The slug is regenerated on update by
     * spatie/laravel-sluggable from the name — which can now change — and
     * Str::slug() of Arabic can collapse to an empty string and pick up a "-1"
     * suffix, so the original slug is pinned back and existing links keep
     * working.
     *
     * @param  array<int, \Illuminate\Database\Eloquent\Model>  $touched
     */
    private function saveTouched(array $touched): void
    {
        foreach ($touched as $model) {
            $originalSlug = $model->getOriginal('slug');
            $model->save();

            if ($model->slug !== $originalSlug && filled($originalSlug)) {
                $model->slug = $originalSlug;
                $model->saveQuietly();
            }
        }
    }

    /**
     * Fix the Arabic AND the English of this facility and its branches: name,
     * description, branch name and branch address. A field is fixed when either
     * side has a problem (empty, wrong language, swapped, copied), and both
     * sides are rewritten together so the pair is consistent. Name and address
     * go through the identical check. Applied immediately, like fix().
     *
     * @return array{applied: list<array{model: string, id: int, field: string, from: string, to: string, from_ar: string, to_ar: string}>, errors: list<string>}
     */
    public function fixBoth(Facility $facility): array
    {
        $pending = $this->pendingBoth($facility);

        if ($pending === []) {
            return ['applied' => [], 'errors' => []];
        }

        $lines = [
            'Facility (for context): '.($facility->getTranslation('name', 'en') ?: $facility->getTranslation('name', 'ar') ?: '—'),
            'Facility type: '.($facility->facilityType?->getTranslation('name', 'en') ?: $facility->facilityType?->getTranslation('name', 'ar') ?: '—'),
            '',
            'Fields to fix:',
        ];

        foreach ($pending as $index => $row) {
            $kind = $row['model'] === 'facility' ? "facility {$row['field']}" : "branch {$row['field']}";
            $place = ! empty($row['context']) ? " (location: {$row['context']})" : '';
            $lines[] = "{$index}. [{$kind}]{$place} Arabic box: ".json_encode($row['ar'], JSON_UNESCAPED_UNICODE)
                .' | English box: '.json_encode($row['en'], JSON_UNESCAPED_UNICODE);
        }

        $answers = $this->ai->json($this->bothLanguagesPrompt(), implode("\n", $lines), 4096);

        $applied = [];
        $errors = [];

        DB::transaction(function () use ($facility, $pending, $answers, &$applied, &$errors) {
            $branches = $facility->branches->keyBy('id');

            /** @var array<int, \Illuminate\Database\Eloquent\Model> $touched */
            $touched = [];

            foreach ($pending as $index => $row) {
                $ar = data_get($answers, "{$index}.ar");
                $en = data_get($answers, "{$index}.en");
                $ar = is_scalar($ar) ? trim((string) $ar) : '';
                $en = is_scalar($en) ? trim((string) $en) : '';

                // Only a pair that is right on both sides is used.
                if ($ar === '' || $en === '' || ! $this->looksArabic($ar) || $this->looksArabic($en)) {
                    $errors[] = "Could not fix {$row['model']} #{$row['id']} {$row['field']}.";

                    continue;
                }

                $model = $row['model'] === 'facility' ? $facility : $branches->get($row['id']);

                if ($model === null) {
                    continue;
                }

                $model->setTranslation($row['field'], 'ar', $ar);
                $model->setTranslation($row['field'], 'en', $en);
                $touched[spl_object_id($model)] ??= $model;

                $applied[] = [
                    'model' => $row['model'],
                    'id' => $row['id'],
                    'field' => $row['field'],
                    'from' => $row['en'],
                    'to' => $en,
                    'from_ar' => $row['ar'],
                    'to_ar' => $ar,
                ];
            }

            $this->saveTouched($touched);
        });

        if ($applied !== []) {
            Log::info('Facility bilingual fix applied', [
                'facility_id' => $facility->id,
                'facility_slug' => $facility->slug,
                'fields' => array_map(fn ($a) => "{$a['model']}#{$a['id']}.{$a['field']}", $applied),
            ]);
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Every facility and branch field where either language has a problem.
     *
     * @return list<array{model: string, id: int, field: string, ar: string, en: string, context?: string}>
     */
    private function pendingBoth(Facility $facility): array
    {
        $rows = [];

        $consider = function (string $modelKind, $model, string $field, string $context = '') use (&$rows) {
            $ar = trim((string) ($model->getTranslation($field, 'ar', false) ?? ''));
            $en = trim((string) ($model->getTranslation($field, 'en', false) ?? ''));

            if (($ar === '' && $en === '') || ! $this->languagesNeedFix($ar, $en)) {
                return;
            }

            $rows[] = ['model' => $modelKind, 'id' => $model->id, 'field' => $field, 'ar' => $ar, 'en' => $en, 'context' => $context];
        };

        foreach (self::FACILITY_FIELDS as $field) {
            $consider('facility', $facility, $field);
        }

        foreach ($facility->branches as $branch) {
            array_push($rows, ...$this->branchFieldRows($branch));
        }

        return $rows;
    }

    /**
     * The name and address of one branch, each only when a language is wrong.
     *
     * The single place the branch rule lives, so name and address can never be
     * judged differently: a field needs fixing when it is empty on either side,
     * in the wrong language, or a verbatim copy — see {@see languagesNeedFix()}.
     * A field with nothing in either language is left out: there is nothing to
     * translate from.
     *
     * @return list<array{model: string, id: int, field: string, ar: string, en: string, context: string}>
     */
    private function branchFieldRows(FacilityBranch $branch): array
    {
        $place = collect([
            $branch->city?->getTranslation('name', 'en') ?: $branch->city?->getTranslation('name', 'ar'),
            $branch->governorate?->getTranslation('name', 'en') ?: $branch->governorate?->getTranslation('name', 'ar'),
        ])->filter()->implode(', ');

        $rows = [];

        foreach (self::BRANCH_FIELDS as $field) {
            $ar = trim((string) ($branch->getTranslation($field, 'ar', false) ?? ''));
            $en = trim((string) ($branch->getTranslation($field, 'en', false) ?? ''));

            if (($ar === '' && $en === '') || ! $this->languagesNeedFix($ar, $en)) {
                continue;
            }

            $rows[] = ['model' => 'branch', 'id' => $branch->id, 'field' => $field, 'ar' => $ar, 'en' => $en, 'context' => $place];
        }

        return $rows;
    }

    /**
     * Does this branch have a name or address with a language to fix? Reads only
     * the branch's own columns, so it is cheap enough to run over the whole list.
     */
    public function branchNeedsTranslation(FacilityBranch $branch): bool
    {
        return $this->branchFieldRows($branch) !== [];
    }

    /**
     * Fix the Arabic AND English of one branch's name and address, and save it.
     *
     * The branch-list sweep's unit of work, where {@see fixBoth()} is the
     * facility's. Only a pair that is right on both sides is written; a field
     * the model could not fix is reported and left exactly as it was. Saved
     * quietly, so the slug — which is derived from the name — does not move and
     * no link to the branch breaks.
     *
     * @return array{applied: list<array{field: string, from: string, to: string, from_ar: string, to_ar: string}>, errors: list<string>}
     */
    public function fixBranch(FacilityBranch $branch): array
    {
        $pending = $this->branchFieldRows($branch);

        if ($pending === []) {
            return ['applied' => [], 'errors' => []];
        }

        $lines = [
            'Facility (for context): '.($branch->facility?->getTranslation('name', 'en') ?: $branch->facility?->getTranslation('name', 'ar') ?: '—'),
            'Facility type: '.($branch->facility?->facilityType?->getTranslation('name', 'en') ?: $branch->facility?->facilityType?->getTranslation('name', 'ar') ?: '—'),
            '',
            'Fields to fix:',
        ];

        foreach ($pending as $index => $row) {
            $place = $row['context'] !== '' ? " (location: {$row['context']})" : '';
            $lines[] = "{$index}. [branch {$row['field']}]{$place} Arabic box: ".json_encode($row['ar'], JSON_UNESCAPED_UNICODE)
                .' | English box: '.json_encode($row['en'], JSON_UNESCAPED_UNICODE);
        }

        $answers = $this->ai->json($this->bothLanguagesPrompt(), implode("\n", $lines), 1024);

        $applied = [];
        $errors = [];

        foreach ($pending as $index => $row) {
            $ar = data_get($answers, "{$index}.ar");
            $en = data_get($answers, "{$index}.en");
            $ar = is_scalar($ar) ? trim((string) $ar) : '';
            $en = is_scalar($en) ? trim((string) $en) : '';

            // Only a pair that is right on both sides is used.
            if ($ar === '' || $en === '' || ! $this->looksArabic($ar) || $this->looksArabic($en)) {
                $errors[] = "Could not fix the {$row['field']}.";

                continue;
            }

            $branch->setTranslation($row['field'], 'ar', $ar);
            $branch->setTranslation($row['field'], 'en', $en);

            $applied[] = [
                'field' => $row['field'],
                'from' => $row['en'],
                'to' => $en,
                'from_ar' => $row['ar'],
                'to_ar' => $ar,
            ];
        }

        if ($applied !== []) {
            $branch->saveQuietly();

            Log::info('Branch bilingual fix applied', [
                'branch_id' => $branch->id,
                'facility_id' => $branch->facility_id,
                'fields' => array_column($applied, 'field'),
            ]);
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * English for Arabic values that only exist in an open form — the facility
     * form's branch modal, where the branch may not be saved yet and so there
     * is no model to walk. Nothing is written here; the answers go back into
     * the form for the admin to check before saving, the same way the
     * "Find on map with AI" button fills the coordinates.
     *
     * @param  array<string, array{kind: string, ar: string}>  $fields  keyed by the form field
     * @param  array<string, string>  $context  free-form lines given to the model as context
     * @return array<string, string> the same keys, with the English value; a
     *                               field the model could not translate is left out
     */
    public function translateFields(array $fields, array $context = []): array
    {
        $pending = [];
        foreach ($fields as $key => $field) {
            $ar = trim((string) ($field['ar'] ?? ''));
            if ($ar === '') {
                continue;
            }

            $pending[] = ['key' => $key, 'kind' => (string) ($field['kind'] ?? $key), 'ar' => $ar];
        }

        if ($pending === []) {
            return [];
        }

        $lines = [];
        foreach ($context as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }
        $lines[] = '';
        $lines[] = 'Fields to translate:';

        foreach ($pending as $index => $row) {
            $lines[] = "{$index}. [{$row['kind']}] Arabic: {$row['ar']}";
        }

        $answers = $this->ai->json($this->systemPrompt(), implode("\n", $lines), 1024);

        $out = [];
        foreach ($pending as $index => $row) {
            $value = data_get($answers, (string) $index);
            $value = is_scalar($value) ? trim((string) $value) : '';

            // A value that came back still in Arabic is no better than the one
            // already in the box, so it is dropped rather than written over it.
            if ($value === '' || $this->looksArabic($value)) {
                continue;
            }

            $out[$row['key']] = $value;
        }

        return $out;
    }

    /**
     * Repairs both languages of values that only exist in an open form.
     *
     * A field is worth fixing when either side has a problem: a box left empty,
     * Arabic typed into the English box (or the reverse), the two boxes
     * swapped, or the same text copied into both. The model is shown what is in
     * both boxes — the real content may sit in either, or in the wrong one —
     * and returns a correct Arabic and English pair, so the two ends up
     * consistent rather than one language patched against a broken other.
     *
     * Fields with nothing typed at all, or where both sides already look right,
     * are left out. Nothing is written here; the answers go back into the form.
     *
     * @param  array<string, array{kind: string, ar: string, en: string}>  $fields  keyed by the form field
     * @param  array<string, string>  $context  free-form lines given to the model as context
     * @return array<string, array{ar: string, en: string}> only the fields that were fixed
     */
    public function fixLanguages(array $fields, array $context = []): array
    {
        $pending = [];
        foreach ($fields as $key => $field) {
            $ar = trim((string) ($field['ar'] ?? ''));
            $en = trim((string) ($field['en'] ?? ''));

            if (($ar === '' && $en === '') || ! $this->languagesNeedFix($ar, $en)) {
                continue;
            }

            $pending[] = ['key' => $key, 'kind' => (string) ($field['kind'] ?? $key), 'ar' => $ar, 'en' => $en];
        }

        if ($pending === []) {
            return [];
        }

        $lines = [];
        foreach ($context as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }
        $lines[] = '';
        $lines[] = 'Fields to fix:';

        foreach ($pending as $index => $row) {
            $lines[] = "{$index}. [{$row['kind']}] Arabic box: ".json_encode($row['ar'], JSON_UNESCAPED_UNICODE)
                .' | English box: '.json_encode($row['en'], JSON_UNESCAPED_UNICODE);
        }

        $answers = $this->ai->json($this->bothLanguagesPrompt(), implode("\n", $lines), 2048);

        $out = [];
        foreach ($pending as $index => $row) {
            $ar = data_get($answers, "{$index}.ar");
            $en = data_get($answers, "{$index}.en");
            $ar = is_scalar($ar) ? trim((string) $ar) : '';
            $en = is_scalar($en) ? trim((string) $en) : '';

            // Only a pair that is right on both sides is used: Arabic in the
            // Arabic box, none in the English one. Anything else is no better
            // than what the admin already has.
            if ($ar === '' || $en === '' || ! $this->looksArabic($ar) || $this->looksArabic($en)) {
                continue;
            }

            $out[$row['key']] = ['ar' => $ar, 'en' => $en];
        }

        return $out;
    }

    /**
     * Is either side of this Arabic / English pair wrong? An Arabic box with
     * only Latin letters in it counts, as does an English box that is empty,
     * holds Arabic, or repeats the Arabic verbatim.
     */
    public function languagesNeedFix(string $ar, string $en): bool
    {
        $ar = trim($ar);
        $en = trim($en);

        if ($ar === '' || $en === '') {
            return true;
        }

        $arabicBoxIsLatin = ! $this->looksArabic($ar) && preg_match('/\p{Latin}/u', $ar);

        return $arabicBoxIsLatin || $this->looksArabic($en) || mb_strtolower($en) === mb_strtolower($ar);
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

    private function bothLanguagesPrompt(): string
    {
        return <<<'PROMPT'
        You clean up bilingual (Arabic + English) data for an Egyptian medical directory.

        You are given a numbered list of fields. Each has an "Arabic box" and an
        "English box" as the admin typed them. Either box may be empty, may hold the
        wrong language (English in the Arabic box, Arabic in the English box), the two
        may be swapped, or one may just be a poor rendering of the other. The real
        content may be in either box — work out what it is, then return BOTH sides
        correctly.

        Return ONLY a JSON object mapping each number (as a string key) to an object
        {"ar": "...", "en": "..."}. No other keys, no commentary.

        Rules:
        - "ar" must be written in Arabic script and "en" in Latin script, with no
          Arabic characters. Never leave either one in the wrong language or empty.
        - Where a box already holds a correct value in its own language, keep it as
          it is and build the other side from it. Only rewrite what is wrong.
        - Never invent information that is not in either box. Keep numbers as digits.
        - name (facility / branch): render proper nouns by their common spelling or
          Egyptian-Arabic transliteration and translate the descriptor (مستشفى →
          "Hospital", عيادة → "Clinic", مركز → "Center", معمل → "Lab", صيدلية →
          "Pharmacy", د. → "Dr."). English in Title Case. No trailing punctuation.
        - address: a natural street address in each language. Transliterate street and
          area names, translate generic words (شارع → "Street", ميدان → "Square",
          برج → "Tower", الدور → "Floor").
        - description: fluent, faithful prose in each language, same meaning and tone
          in both. Keep any HTML tags exactly as they are; only the text between them
          changes language.
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
