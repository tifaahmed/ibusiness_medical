<?php

namespace App\Services;

use App\Services\Ai\GeminiClient;
use App\Support\SiteSettings;
use RuntimeException;

/**
 * Writes bilingual (ar/en) SEO copy for a facility with Gemini.
 *
 * Backs the "Generate SEO with AI" button on the admin facility form and the
 * "Fill SEO with AI" sweep on the facility list. The caller passes the facility
 * details the admin has already filled in and gets back meta
 * title/description/keywords for both locales, clamped to the lengths search
 * engines actually render. Shares the transport (and the free-tier rate-limit
 * handling) with {@see ProductSeoGenerator}.
 */
class FacilitySeoGenerator
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
     * @param  array{name?: array, description?: array, facility_type?: string|null, discount_percent?: mixed, governorates?: array, cities?: array}  $context
     * @return array{meta_title: array{ar: string, en: string}, meta_description: array{ar: string, en: string}, meta_keywords: array{ar: string, en: string}}
     *
     * @throws RuntimeException when the key is missing or the API call fails.
     * @throws \App\Services\Ai\RateLimitException when the provider is rate-limited.
     */
    public function generate(array $context): array
    {
        $decoded = $this->ai->json($this->systemPrompt(), $this->userPrompt($context));

        return $this->normalize($decoded);
    }

    private function systemPrompt(): string
    {
        return str_replace('{brand}', $this->brandName(), <<<'PROMPT'
        You are an SEO copywriter for {brand}, an Egyptian medical discount-card
        network. You write metadata for the public page of a single partner medical
        facility (clinic, hospital, lab, pharmacy, scan centre, etc.).

        Write for real Egyptian patients searching in Arabic and English. Be concrete and
        specific to the facility given — never generic filler, never invented facts such
        as awards, doctor names, ratings, opening hours, or prices that were not provided.

        Respond with ONLY a JSON object in exactly this shape:
        {
          "meta_title":       {"ar": "...", "en": "..."},
          "meta_description": {"ar": "...", "en": "..."},
          "meta_keywords":    {"ar": "...", "en": "..."}
        }

        Rules:
        - meta_title: at most 60 characters, lead with the facility name, no site-name suffix.
        - meta_description: 140-160 characters, one or two sentences, end with a natural
          call to action. Mention the discount only if a discount percentage was provided.
        - meta_keywords: 6-10 comma-separated terms, no hashtags, no repetition.
        - The Arabic must be natural Modern Standard Arabic as used in Egypt, not a
          word-for-word translation of the English.
        - Use only the facts supplied below.
        - The brand is called "{brand}" — never write it any other way (never "ASH").
        PROMPT);
    }

    /**
     * The name of this site as set in the admin settings ("Deilar"), so the AI
     * writes the brand the way the project is actually called.
     */
    private function brandName(): string
    {
        return (string) (SiteSettings::get('deilar_name', config('app.name')) ?: config('app.name'));
    }

    private function userPrompt(array $context): string
    {
        $lines = [];

        $lines[] = 'Facility name (AR): '.($this->text($context, 'name.ar') ?: '—');
        $lines[] = 'Facility name (EN): '.($this->text($context, 'name.en') ?: '—');

        $type = $this->text($context, 'facility_type');
        $lines[] = 'Facility type: '.($type ?: '—');

        $discount = data_get($context, 'discount_percent');
        $lines[] = 'Discount for card holders: '.(filled($discount) ? $discount.'%' : 'not specified');

        $govs = array_filter((array) data_get($context, 'governorates', []));
        $lines[] = 'Governorates covered: '.($govs ? implode(', ', $govs) : 'not specified');

        $cities = array_filter((array) data_get($context, 'cities', []));
        $lines[] = 'Cities covered: '.($cities ? implode(', ', $cities) : 'not specified');

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
     * Force the model's answer into the exact shape/limits the form expects,
     * so a sloppy response can never write oversized values into the DB.
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
