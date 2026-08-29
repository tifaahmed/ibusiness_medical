<?php

namespace App\Services;

use App\Services\Ai\GeminiClient;
use RuntimeException;

/**
 * Writes bilingual (ar/en) SEO copy for a shop product with Gemini.
 *
 * The product counterpart of {@see FacilitySeoGenerator}: the caller passes the
 * product details the admin has already entered and gets back meta
 * title/description/keywords for both locales, clamped to the lengths search
 * engines actually render.
 */
class ProductSeoGenerator
{
    public const TITLE_MAX = 60;

    public const DESCRIPTION_MAX = 160;

    public const KEYWORDS_MAX = 255;

    public function __construct(private readonly GeminiClient $ai) {}

    /**
     * Whether a key is configured. The UI hides/disables the button when not.
     */
    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  array{name?: array, short_subject?: array, description?: array, product_type?: string|null, old_price?: mixed, new_price?: mixed, tags?: array}  $context
     * @return array{meta_title: array{ar: string, en: string}, meta_description: array{ar: string, en: string}, meta_keywords: array{ar: string, en: string}}
     *
     * @throws RuntimeException when the key is missing or the API call fails.
     */
    public function generate(array $context): array
    {
        $decoded = $this->ai->json($this->systemPrompt(), $this->userPrompt($context));

        return $this->normalize($decoded);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are an SEO copywriter for ASH Health Care, an Egyptian medical discount-card
        network that also runs an online shop of medical and health products.

        You write metadata for the public page of a single product. Write for real
        Egyptian shoppers searching in Arabic and English. Be concrete and specific to
        the product given — never generic filler, never invented facts such as brand
        names, certifications, specifications, ratings or prices that were not provided.

        Respond with ONLY a JSON object in exactly this shape:
        {
          "meta_title":       {"ar": "...", "en": "..."},
          "meta_description": {"ar": "...", "en": "..."},
          "meta_keywords":    {"ar": "...", "en": "..."}
        }

        Rules:
        - meta_title: at most 60 characters, lead with the product name, no site-name suffix.
        - meta_description: 120-160 characters, one or two sentences, end with a natural
          call to action. Mention a discount only if old and new prices were both provided.
        - meta_keywords: 6-10 comma-separated terms, no hashtags, no repetition.
        - The Arabic must be natural Modern Standard Arabic as used in Egypt, not a
          word-for-word translation of the English.
        - Use only the facts supplied below.
        PROMPT;
    }

    private function userPrompt(array $context): string
    {
        $lines = [];

        $lines[] = 'Product name (AR): '.($this->text($context, 'name.ar') ?: '—');
        $lines[] = 'Product name (EN): '.($this->text($context, 'name.en') ?: '—');

        $lines[] = 'Short subject (AR): '.($this->text($context, 'short_subject.ar') ?: '—');
        $lines[] = 'Short subject (EN): '.($this->text($context, 'short_subject.en') ?: '—');

        $type = $this->text($context, 'product_type');
        $lines[] = 'Product category: '.($type ?: '—');

        $old = data_get($context, 'old_price');
        $new = data_get($context, 'new_price');
        if (filled($old) && filled($new) && (float) $old > (float) $new) {
            $lines[] = "Price: was {$old}, now {$new} EGP (on sale)";
        } elseif (filled($new)) {
            $lines[] = "Price: {$new} EGP";
        } else {
            $lines[] = 'Price: not specified';
        }

        $tags = array_filter((array) data_get($context, 'tags', []));
        $lines[] = 'Tags: '.($tags ? implode(', ', $tags) : 'not specified');

        $descAr = $this->text($context, 'description.ar');
        $descEn = $this->text($context, 'description.en');
        $lines[] = 'Existing description (AR): '.($descAr ? mb_substr($descAr, 0, 1200) : '—');
        $lines[] = 'Existing description (EN): '.($descEn ? mb_substr($descEn, 0, 1200) : '—');

        return implode("\n", $lines);
    }

    /**
     * Descriptions arrive as HTML from the rich-text editor; strip it so the
     * model reads prose rather than markup.
     */
    private function text(array $context, string $key): string
    {
        $value = data_get($context, $key);

        if (! is_string($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value))) ?? '');
    }

    /**
     * Force the model's answer into the exact shape/limits the form expects, so
     * a sloppy response can never write oversized values into the DB.
     *
     * @return array{meta_title: array{ar: string, en: string}, meta_description: array{ar: string, en: string}, meta_keywords: array{ar: string, en: string}}
     */
    private function normalize(array $decoded): array
    {
        $limits = [
            'meta_title' => self::TITLE_MAX,
            'meta_description' => self::DESCRIPTION_MAX,
            'meta_keywords' => self::KEYWORDS_MAX,
        ];

        $result = [];

        foreach ($limits as $field => $max) {
            foreach (['ar', 'en'] as $locale) {
                $value = data_get($decoded, "{$field}.{$locale}");
                $value = is_scalar($value) ? trim((string) $value) : '';
                $result[$field][$locale] = mb_substr($value, 0, $max);
            }
        }

        return $result;
    }
}
