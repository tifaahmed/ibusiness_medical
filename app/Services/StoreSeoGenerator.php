<?php

namespace App\Services;

use App\Services\Ai\GeminiClient;
use App\Support\SiteSettings;

/**
 * Writes bilingual (ar/en) SEO copy for a store with Gemini, from what the
 * admin has already entered. Clamped to the lengths search engines render.
 * Nothing is saved here; the values go back into the open form.
 */
class StoreSeoGenerator
{
    public const TITLE_MAX = 60;

    public const DESCRIPTION_MAX = 160;

    public const KEYWORDS_MAX = 255;

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{meta_title: array{ar: string, en: string}, meta_description: array{ar: string, en: string}, meta_keywords: array{ar: string, en: string}}
     *
     * @throws \RuntimeException when the key is missing or the API call fails.
     */
    public function generate(array $context): array
    {
        $decoded = $this->ai->json($this->systemPrompt(), $this->userPrompt($context));

        $limits = ['meta_title' => self::TITLE_MAX, 'meta_description' => self::DESCRIPTION_MAX, 'meta_keywords' => self::KEYWORDS_MAX];
        $result = [];
        foreach ($limits as $field => $max) {
            foreach (['ar', 'en'] as $locale) {
                $value = data_get($decoded, "{$field}.{$locale}");
                $result[$field][$locale] = mb_substr(is_scalar($value) ? trim((string) $value) : '', 0, $max);
            }
        }

        return $result;
    }

    private function systemPrompt(): string
    {
        $brand = (string) (SiteSettings::get('deilar_name', config('app.name')) ?: config('app.name'));

        return str_replace('{brand}', $brand, <<<'PROMPT'
        You are an SEO copywriter for {brand}, an Egyptian medical discount-card network
        whose members get offers from partner stores.

        You write metadata for the public page of ONE store. Write for real Egyptian
        shoppers searching in Arabic and English. Be concrete and specific to the store
        given — never generic filler, never invented facts (brands, certifications,
        branches, ratings, prices, discounts) that were not provided.

        Respond with ONLY a JSON object in exactly this shape:
        {
          "meta_title":       {"ar": "...", "en": "..."},
          "meta_description": {"ar": "...", "en": "..."},
          "meta_keywords":    {"ar": "...", "en": "..."}
        }

        Rules:
        - meta_title: at most 60 characters, lead with the store name, no site-name suffix.
        - meta_description: 120-160 characters, one or two sentences, end with a natural
          call to action. Mention a discount only if an offer percentage was provided.
        - meta_keywords: 6-10 comma-separated terms, no hashtags, no repetition.
        - The Arabic must be natural Modern Standard Arabic as used in Egypt, not a
          word-for-word translation of the English.
        - The brand is called "{brand}" — never write it any other way.
        PROMPT);
    }

    private function userPrompt(array $c): string
    {
        $text = fn (string $key) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) data_get($c, $key, '')))) ?? '');
        $list = fn (string $key) => implode(', ', array_filter((array) data_get($c, $key, [])));

        $from = data_get($c, 'offer_percent_from');
        $to = data_get($c, 'offer_percent_to');

        return implode("\n", [
            'Store name (AR): '.($text('title.ar') ?: '—'),
            'Store name (EN): '.($text('title.en') ?: '—'),
            'Categories: '.($list('categories') ?: '—'),
            'Tags: '.($list('tags') ?: '—'),
            'Offer: '.(filled($from) || filled($to) ? ($from ?: 0).'% – '.($to ?: 0).'% off for members' : 'not specified'),
            'Short description (AR): '.(mb_substr($text('short_description.ar'), 0, 500) ?: '—'),
            'Short description (EN): '.(mb_substr($text('short_description.en'), 0, 500) ?: '—'),
            'Description (AR): '.(mb_substr($text('description.ar'), 0, 1200) ?: '—'),
            'Description (EN): '.(mb_substr($text('description.en'), 0, 1200) ?: '—'),
        ]);
    }
}
