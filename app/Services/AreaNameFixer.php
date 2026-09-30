<?php

namespace App\Services;

use App\Models\Area;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Collection;

/**
 * Repairs the names of imported areas with Gemini.
 *
 * The census (OCHA COD-AB) publishes many area names with the spaces stripped
 * and ى spelled ي ("مصطفيكاملوبولوكلي"), and none has an English name. For a
 * batch of areas this asks for the same Arabic name written properly and an
 * English name, then checks the Arabic answer before trusting it: it must be
 * the ORIGINAL LETTERS, only re-spaced (and ى/ي, ة/ه, أإآ/ا normalised) — a
 * rewrite, a dropped or an added word is thrown away and the original Arabic
 * kept. English is only used when it holds no Arabic.
 */
class AreaNameFixer
{
    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  Collection<int, Area>  $areas  loaded with city.governorate
     * @return array<int, array{ar: string, en: string|null, ar_changed: bool, ar_rejected: bool}>  keyed by area id
     */
    public function propose(Collection $areas): array
    {
        if ($areas->isEmpty()) {
            return [];
        }

        $answers = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($areas),
            768 + ($areas->count() * 90),
        );

        $out = [];
        foreach ($areas as $area) {
            $original = trim((string) $area->getTranslation('name', 'ar', false));
            $answer = data_get($answers, (string) $area->id);
            if (! is_array($answer)) {
                continue;
            }

            $ar = trim(preg_replace('/\s+/u', ' ', (string) ($answer['ar'] ?? '')));
            $en = trim((string) ($answer['en'] ?? ''));

            $arOk = $ar !== '' && self::sameLetters($original, $ar);
            $enOk = $en !== '' && ! preg_match('/\p{Arabic}/u', $en) && mb_strlen($en) <= 120;

            if (! $enOk && ! $arOk) {
                continue;
            }

            $out[$area->id] = [
                'ar' => $arOk ? $ar : $original,
                'en' => $enOk ? $en : null,
                'ar_changed' => $arOk && $ar !== $original,
                'ar_rejected' => $ar !== '' && ! $arOk,
            ];
        }

        return $out;
    }

    /**
     * Saves a proposal on the area: the Arabic only when it changed, the English
     * only when there is one. Areas carry no slug hook, so a plain save is safe.
     */
    public function apply(Area $area, array $proposal): bool
    {
        $changed = false;

        if ($proposal['ar_changed']) {
            $area->setTranslation('name', 'ar', $proposal['ar']);
            $changed = true;
        }
        if (filled($proposal['en'])) {
            $area->setTranslation('name', 'en', $proposal['en']);
            $changed = true;
        }

        return $changed && $area->save();
    }

    /**
     * Do two Arabic strings hold the same letters, ignoring spaces, marks and the
     * spelling variants the census mixes (ى/ي, ة/ه, hamza forms of alef)?
     */
    public static function sameLetters(string $a, string $b): bool
    {
        return self::letters($a) !== '' && self::letters($a) === self::letters($b);
    }

    private static function letters(string $value): string
    {
        $value = preg_replace('/[\s\x{064B}-\x{065F}\x{0670}\x{0640}]+/u', '', $value);

        return strtr($value, [
            'ى' => 'ي', 'ئ' => 'ي', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ؤ' => 'و',
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You clean up Arabic place names from the Egyptian census (CAPMAS / OCHA) for a medical directory and give each an English name.

        You are given numbered areas (neighbourhood / village units) with the city and governorate they sit in. The Arabic names are often written with the spaces between words removed and with ي where ى belongs, e.g. "مصطفيكاملوبولوكلي" is "مصطفى كامل وبولكلي" (two neighbourhoods).

        Return ONLY a JSON object mapping each number (as a string key) to {"ar": ..., "en": ...}.

        ar: the SAME Arabic name written properly. Only insert the missing spaces between words (before/after "و" when it is a conjunction, after "أبو"/"عزبة"/"كفر"/"ابو"/"ال..." words, between the words of a compound) and correct final ي→ى / ه→ة where the standard spelling needs it. NEVER add, drop or replace other letters or words, never translate, never add commentary. If the name is already correct, return it unchanged.

        en: the common English name, transliterated the way it is normally written for Egyptian places (e.g. أبو المطامير → "Abu El Matamir", عزبة النخل → "Ezbet El Nakhl", مصطفى كامل → "Mostafa Kamel"). Title Case, no trailing punctuation. For a name that joins several places, join them with " & ". Never leave the English in Arabic.
        PROMPT;
    }

    /**
     * @param  Collection<int, Area>  $areas
     */
    private function userPrompt(Collection $areas): string
    {
        $lines = ['Areas:'];
        foreach ($areas as $area) {
            $city = $area->city?->getTranslation('name', 'en', false) ?: $area->city?->getTranslation('name', 'ar', false) ?: '—';
            $gov = $area->city?->governorate?->getTranslation('name', 'en', false) ?: '—';
            $lines[] = "{$area->id}. Arabic: {$area->getTranslation('name', 'ar', false)} | city: {$city} | governorate: {$gov}";
        }

        return implode("\n", $lines);
    }
}
