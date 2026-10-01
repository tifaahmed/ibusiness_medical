<?php

namespace App\Services;

use App\Models\Tag;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Collection;

/**
 * Repairs a tag's Arabic AND English name with Gemini.
 *
 * Tags are often typed in one language only and copied into both boxes
 * ("Radiology / Radiology"), or with Arabic in the English box. A tag needs a
 * fix when either side is blank, in the wrong script, or the two are the same
 * text. The model gets both values and returns both; whichever side was
 * already right is expected back unchanged.
 *
 * {@see propose()} writes nothing — the list page shows the proposals for the
 * admin to tick, and only the ticked ones are saved.
 */
class TagTranslationFixer
{
    /** Tags per AI request; small enough that one answer never runs out of tokens. */
    private const CHUNK = 40;

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    public function needsFix(?string $ar, ?string $en): bool
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        if ($ar === '' && $en === '') {
            return false;
        }

        return ! $this->isArabicName($ar) || ! $this->isEnglishName($en) || $ar === $en;
    }

    /**
     * @param  Collection<int, Tag>  $tags
     * @return list<array{id: int, icon: string|null, color: string|null, from: array{ar: string, en: string}, to: array{ar: string, en: string}}>
     */
    public function propose(Collection $tags): array
    {
        $pending = $tags->filter(fn (Tag $tag) => $this->needsFix(
            $tag->getTranslation('name', 'ar', false),
            $tag->getTranslation('name', 'en', false),
        ))->values();

        $out = [];

        foreach ($pending->chunk(self::CHUNK) as $chunk) {
            $lines = ['Tags to fix (id: current Arabic value | current English value):'];
            foreach ($chunk as $tag) {
                $ar = json_encode((string) $tag->getTranslation('name', 'ar', false), JSON_UNESCAPED_UNICODE);
                $en = json_encode((string) $tag->getTranslation('name', 'en', false), JSON_UNESCAPED_UNICODE);
                $lines[] = "{$tag->id}: ar={$ar} | en={$en}";
            }

            $answers = $this->ai->json($this->systemPrompt(), implode("\n", $lines), 4096);

            foreach ($chunk as $tag) {
                $answer = $answers[(string) $tag->id] ?? null;
                if (! is_array($answer)) {
                    continue;
                }

                $ar = $this->clean($answer['ar'] ?? null);
                $en = $this->clean($answer['en'] ?? null);

                if (! $this->isArabicName($ar) || ! $this->isEnglishName($en)) {
                    continue;
                }

                $from = [
                    'ar' => (string) $tag->getTranslation('name', 'ar', false),
                    'en' => (string) $tag->getTranslation('name', 'en', false),
                ];

                if ($from['ar'] === $ar && $from['en'] === $en) {
                    continue;
                }

                $out[] = [
                    'id' => $tag->id,
                    'icon' => $tag->icon,
                    'color' => $tag->color,
                    'from' => $from,
                    'to' => ['ar' => $ar, 'en' => $en],
                ];
            }
        }

        return $out;
    }

    /** Arabic script, and nothing Latin in it. */
    public function isArabicName(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && preg_match('/\p{Arabic}/u', $value) && ! preg_match('/\p{Latin}/u', $value);
    }

    /** Latin script, and nothing Arabic in it. */
    public function isEnglishName(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && preg_match('/\p{Latin}/u', $value) && ! preg_match('/\p{Arabic}/u', $value);
    }

    private function clean(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return mb_strlen($value) > 60 ? '' : $value;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You fix the names of tags in an Egyptian medical discount-card directory
        (tags such as specialties — Radiology, Dental — and labels — New, Best Offer).
        Each tag has an Arabic name and an English name, but some were typed in the wrong
        box, copied into both boxes, or left empty.

        You get a list of tags with their current values. Return ONLY a JSON object
        mapping each tag id (as a string key) to {"ar": "...", "en": "..."}. No other
        keys, no commentary, no code fences.

        - "ar": the natural Arabic name as used in Egypt, in Arabic script only
          (e.g. Radiology → "الأشعة", Lab & Diagnostics → "التحاليل والتشخيص",
          Dental → "الأسنان", Pharmacy → "صيدلية", General Checkup → "كشف عام").
        - "en": the natural English name, Title Case, Latin script only.
        - Keep the meaning of the existing value. If one side is already correct, return
          it exactly as given. Do not add words, emoji or punctuation.
        - Short: one to four words each.
        PROMPT;
    }
}
