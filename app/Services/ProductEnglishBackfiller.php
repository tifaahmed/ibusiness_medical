<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fills / repairs the English (`en`) translation of a shop product with Gemini,
 * using the Arabic value as the source of truth.
 *
 * The product counterpart of {@see FacilityEnglishBackfiller}. Only `en` values
 * are touched. A value needs a fix when the Arabic side has content and the
 * English side is blank, still holds Arabic characters (pasted into the wrong
 * input), or is a verbatim copy of the Arabic.
 *
 * The result is applied immediately — there is no preview step.
 */
class ProductEnglishBackfiller
{
    /** Product fields that carry prose worth translating. */
    private const FIELDS = ['name', 'short_subject', 'description'];

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * Does this product have an English field to fix?
     */
    public function hasWork(Product $product): bool
    {
        return $this->pending($product) !== [];
    }

    /**
     * Generate and save corrected English for every pending field.
     *
     * @return array{applied: list<array{field: string, from: string, to: string}>, errors: list<string>}
     */
    public function fix(Product $product): array
    {
        $pending = $this->pending($product);

        if ($pending === []) {
            return ['applied' => [], 'errors' => []];
        }

        $answers = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($product, $pending),
            4096,
        );

        $applied = [];
        $errors = [];

        DB::transaction(function () use ($product, $pending, $answers, &$applied, &$errors) {
            foreach ($pending as $index => $row) {
                $value = data_get($answers, (string) $index);
                $value = is_scalar($value) ? trim((string) $value) : '';

                if ($value === '' || $this->looksArabic($value)) {
                    $errors[] = "Could not produce English for the {$row['label']}.";

                    continue;
                }

                $from = (string) ($product->getTranslation($row['field'], 'en') ?? '');
                $product->setTranslation($row['field'], 'en', $value);

                $applied[] = ['field' => $row['field'], 'from' => $from, 'to' => $value];
            }

            if ($applied !== []) {
                // Saving regenerates the slug from the (Arabic) name, which
                // Str::slug() can collapse to "" + a "-1" suffix. The Arabic
                // name is untouched here, so pin the original slug back.
                $originalSlug = $product->getOriginal('slug');
                $product->save();

                if ($product->slug !== $originalSlug && filled($originalSlug)) {
                    $product->slug = $originalSlug;
                    $product->saveQuietly();
                }
            }
        });

        if ($applied !== []) {
            Log::info('Product English backfill applied', [
                'product_id' => $product->id,
                'product_slug' => $product->slug,
                'fields' => array_map(fn ($a) => $a['field'], $applied),
            ]);
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * Every field on the product that needs an English fix, in a stable order.
     *
     * @return list<array{field: string, label: string, ar: string}>
     */
    private function pending(Product $product): array
    {
        $labels = [
            'name' => 'name',
            'short_subject' => 'short description',
            'description' => 'description',
        ];

        $rows = [];

        foreach (self::FIELDS as $field) {
            $ar = (string) ($product->getTranslation($field, 'ar') ?? '');
            $en = (string) ($product->getTranslation($field, 'en') ?? '');

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
        You localise Arabic e-commerce product copy for an Egyptian medical shop into English.

        You are given a numbered list of fields, each with its Arabic value and the
        kind of field it is. Return ONLY a JSON object mapping each number (as a
        string key) to the corrected English string. No other keys, no commentary.

        Rules by field type:
        - name: the product's public title. Use the common English product name;
          keep brand names and model numbers verbatim. Title Case. No trailing
          punctuation.
        - short description: one natural English sentence, faithful to the Arabic.
        - description: fluent, faithful English prose. The Arabic value may contain
          HTML markup — keep every HTML tag and attribute exactly as given and
          translate only the human-readable text between the tags.

        Keep the meaning and tone. Never add facts that are not in the Arabic.
        Never leave a value in Arabic.
        PROMPT;
    }

    /**
     * @param  list<array{field: string, label: string, ar: string}>  $pending
     */
    private function userPrompt(Product $product, array $pending): string
    {
        $lines = [];
        $lines[] = 'Product (for context): '
            .($product->getTranslation('name', 'en')
                ?: $product->getTranslation('name', 'ar')
                ?: '—');
        $lines[] = 'Product category: '
            .($product->productType?->getTranslation('name', 'en')
                ?: $product->productType?->getTranslation('name', 'ar')
                ?: '—');
        $lines[] = '';
        $lines[] = 'Fields to translate:';

        foreach ($pending as $index => $row) {
            $lines[] = "{$index}. [{$row['label']}] Arabic: {$row['ar']}";
        }

        return implode("\n", $lines);
    }
}
