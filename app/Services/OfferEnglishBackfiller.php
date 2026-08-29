<?php

namespace App\Services;

use App\Models\Offer;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fills / repairs the English (`en`) translation of an offer with Gemini, using
 * the Arabic value as the source of truth.
 *
 * The offer counterpart of {@see ProductEnglishBackfiller} and
 * {@see FacilityEnglishBackfiller}. Only `en` values are touched. A value needs
 * a fix when the Arabic side has content and the English side is blank, still
 * holds Arabic characters (pasted into the wrong input), or is a verbatim copy
 * of the Arabic. The result is applied immediately — there is no preview step.
 */
class OfferEnglishBackfiller
{
    /** Offer fields that carry prose worth translating. */
    private const FIELDS = ['title', 'short_description', 'full_description'];

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * Does this offer have an English field to fix?
     */
    public function hasWork(Offer $offer): bool
    {
        return $this->pending($offer) !== [];
    }

    /**
     * Generate and save corrected English for every pending field.
     *
     * @return array{applied: list<array{field: string, from: string, to: string}>, errors: list<string>}
     */
    public function fix(Offer $offer): array
    {
        $pending = $this->pending($offer);

        if ($pending === []) {
            return ['applied' => [], 'errors' => []];
        }

        $answers = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($offer, $pending),
            4096,
        );

        $applied = [];
        $errors = [];

        DB::transaction(function () use ($offer, $pending, $answers, &$applied, &$errors) {
            foreach ($pending as $index => $row) {
                $value = data_get($answers, (string) $index);
                $value = is_scalar($value) ? trim((string) $value) : '';

                if ($value === '' || $this->looksArabic($value)) {
                    $errors[] = "Could not produce English for the {$row['label']}.";

                    continue;
                }

                $from = (string) ($offer->getTranslation($row['field'], 'en') ?? '');
                $offer->setTranslation($row['field'], 'en', $value);

                $applied[] = ['field' => $row['field'], 'from' => $from, 'to' => $value];
            }

            if ($applied !== []) {
                // Saving regenerates the slug from the (Arabic) title, which
                // Str::slug() can collapse to "" + a "-1" suffix. The Arabic
                // title is untouched here, so pin the original slug back.
                $originalSlug = $offer->getOriginal('slug');
                $offer->save();

                if ($offer->slug !== $originalSlug && filled($originalSlug)) {
                    $offer->slug = $originalSlug;
                    $offer->saveQuietly();
                }
            }
        });

        if ($applied !== []) {
            Log::info('Offer English backfill applied', [
                'offer_id' => $offer->id,
                'offer_slug' => $offer->slug,
                'fields' => array_map(fn ($a) => $a['field'], $applied),
            ]);
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Every field on the offer that needs an English fix, in a stable order.
     *
     * @return list<array{field: string, label: string, ar: string}>
     */
    private function pending(Offer $offer): array
    {
        $labels = [
            'title' => 'title',
            'short_description' => 'short description',
            'full_description' => 'full description',
        ];

        $rows = [];

        foreach (self::FIELDS as $field) {
            $ar = (string) ($offer->getTranslation($field, 'ar') ?? '');
            $en = (string) ($offer->getTranslation($field, 'en') ?? '');

            if ($this->needsFix($en, $ar)) {
                $rows[] = ['field' => $field, 'label' => $labels[$field], 'ar' => $ar];
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
        You localise Arabic marketing copy for an Egyptian medical discount-card
        network's special offers into English.

        You are given a numbered list of fields, each with its Arabic value and the
        kind of field it is. Return ONLY a JSON object mapping each number (as a
        string key) to the corrected English string. No other keys, no commentary.

        Rules by field type:
        - title: the offer's public headline. Concise, natural English. Title Case.
          No trailing punctuation.
        - short description: one natural English sentence, faithful to the Arabic.
        - full description: fluent, faithful English prose. The Arabic value may
          contain HTML markup — keep every HTML tag and attribute exactly as given
          and translate only the human-readable text between the tags.

        Keep the meaning and tone. Never add facts that are not in the Arabic.
        Never leave a value in Arabic.
        PROMPT;
    }

    /**
     * @param  list<array{field: string, label: string, ar: string}>  $pending
     */
    private function userPrompt(Offer $offer, array $pending): string
    {
        $lines = [];
        $lines[] = 'Offer (for context): '
            .($offer->getTranslation('title', 'en')
                ?: $offer->getTranslation('title', 'ar')
                ?: '—');
        $lines[] = '';
        $lines[] = 'Fields to translate:';

        foreach ($pending as $index => $row) {
            $lines[] = "{$index}. [{$row['label']}] Arabic: {$row['ar']}";
        }

        return implode("\n", $lines);
    }
}
