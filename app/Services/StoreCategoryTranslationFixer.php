<?php

namespace App\Services;

use App\Models\StoreCategory;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Collection;

/**
 * Fixes a store category's Arabic AND English name with Gemini: Arabic written in the English box (or the reverse),
 * one side empty, or the same text in both. Whichever side is already right comes back unchanged.
 * Nothing is written here: {@see propose()} (the list) and {@see fixPair()} (the open form) hand values back for the
 * admin to check and save. The script checks are the tags' ({@see TagTranslationFixer}) so both pages judge alike.
 */
class StoreCategoryTranslationFixer
{
    private const CHUNK = 40;

    public function __construct(private readonly GeminiClient $ai, private readonly TagTranslationFixer $rules) {}

    public function needsFix(?string $ar, ?string $en): bool
    {
        return $this->rules->needsFix($ar, $en);
    }

    public function isArabicName(?string $value): bool
    {
        return $this->rules->isArabicName($value);
    }

    public function isEnglishName(?string $value): bool
    {
        return $this->rules->isEnglishName($value);
    }

    /**
     * @param  Collection<int, StoreCategory>  $categories
     * @return list<array{id: int, from: array{ar: string, en: string}, to: array{ar: string, en: string}}>
     */
    public function propose(Collection $categories): array
    {
        $pending = $categories->filter(fn (StoreCategory $c) => $this->needsFix(
            $c->getTranslation('name', 'ar', false),
            $c->getTranslation('name', 'en', false),
        ))->values();

        $out = [];

        foreach ($pending->chunk(self::CHUNK) as $chunk) {
            $lines = ['Categories to fix (id: current Arabic value | current English value):'];
            foreach ($chunk as $c) {
                $ar = json_encode((string) $c->getTranslation('name', 'ar', false), JSON_UNESCAPED_UNICODE);
                $en = json_encode((string) $c->getTranslation('name', 'en', false), JSON_UNESCAPED_UNICODE);
                $lines[] = "{$c->id}: ar={$ar} | en={$en}";
            }

            $answers = $this->ai->json($this->systemPrompt(), implode("\n", $lines), 4096);

            foreach ($chunk as $c) {
                $answer = $answers[(string) $c->id] ?? null;
                if (! is_array($answer)) {
                    continue;
                }

                $ar = $this->clean($answer['ar'] ?? null);
                $en = $this->clean($answer['en'] ?? null);

                if (! $this->isArabicName($ar) || ! $this->isEnglishName($en)) {
                    continue;
                }

                $from = ['ar' => (string) $c->getTranslation('name', 'ar', false), 'en' => (string) $c->getTranslation('name', 'en', false)];

                if ($from['ar'] === $ar && $from['en'] === $en) {
                    continue;
                }

                $out[] = ['id' => $c->id, 'from' => $from, 'to' => ['ar' => $ar, 'en' => $en]];
            }
        }

        return $out;
    }

    /**
     * One name, as typed in the form: the Arabic and English that should stand. Null when the AI gave nothing usable.
     *
     * @return array{ar: string, en: string}|null
     */
    public function fixPair(?string $ar, ?string $en): ?array
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        if ($ar === '' && $en === '') {
            return null;
        }

        $answers = $this->ai->json($this->systemPrompt(), 'Categories to fix (id: current Arabic value | current English value):'."\n".'1: ar='.json_encode($ar, JSON_UNESCAPED_UNICODE).' | en='.json_encode($en, JSON_UNESCAPED_UNICODE), 512);
        $answer = $answers['1'] ?? null;

        if (! is_array($answer)) {
            return null;
        }

        $fixedAr = $this->clean($answer['ar'] ?? null);
        $fixedEn = $this->clean($answer['en'] ?? null);

        return $this->isArabicName($fixedAr) && $this->isEnglishName($fixedEn) ? ['ar' => $fixedAr, 'en' => $fixedEn] : null;
    }

    private function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return mb_strlen($value) > 80 ? '' : $value;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You fix the names of store categories in an Egyptian medical/health shop directory
        (for example Pharmacies, Medical Equipment, Vitamins & Supplements, Baby Care, Optics).
        Each category has an Arabic name and an English name, but some were typed in the wrong
        box, copied into both boxes, or left empty.

        You get a list of categories with their current values. Return ONLY a JSON object
        mapping each id (as a string key) to {"ar": "...", "en": "..."}. No other keys,
        no commentary, no code fences.

        - "ar": the natural Arabic name as used in Egypt, Arabic script only.
        - "en": the natural English name, Title Case, Latin script only.
        - If one side is already correct and in the right language, return it exactly as given;
          translate it into the other side. Keep the meaning; add no words, emoji or punctuation.
        - Short: one to five words each.
        PROMPT;
    }
}
