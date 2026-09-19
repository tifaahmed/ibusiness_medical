<?php

namespace App\Services;

use App\Services\Ai\GeminiClient;

/**
 * Rewrites a facility description into a tidier layout with Gemini: short
 * headings with an icon (emoji), bullet points instead of long paragraphs.
 *
 * Only the presentation changes — every fact in the original (names, numbers,
 * phones, addresses, prices, links, images) has to survive. Each language is
 * rewritten in its own language, and BOTH languages always come back: one that
 * is empty is filled with a faithful translation of the other, so the button
 * never leaves the Arabic and English boxes out of step. Nothing is saved here; the HTML goes back into
 * the open form for the admin to check.
 */
class FacilityDescriptionEnhancer
{
    /** Tags the layout may use, everything else is stripped from the answer. */
    private const ALLOWED_TAGS = '<p><h3><ul><ol><li><strong><em><br><a><img>';

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  array{ar?: string|null, en?: string|null}  $description  HTML per language
     * @param  array<string, string>  $context  free-form lines given to the model as context
     * @return array<string, string> the enhanced HTML per language; a language the
     *                               model got wrong twice is left out
     */
    public function enhance(array $description, array $context = []): array
    {
        $written = [];
        foreach (['ar', 'en'] as $locale) {
            $html = trim((string) ($description[$locale] ?? ''));
            if ($this->hasText($html)) {
                $written[$locale] = $html;
            }
        }

        if ($written === []) {
            return [];
        }

        // Both languages are always asked for. What a language is checked
        // against is its own text, or — when it is empty — the other language's,
        // which is what it is being translated from.
        $pending = [];
        $lines = [];
        foreach ($context as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }
        $lines[] = '';
        $lines[] = 'Descriptions to reorganise (JSON key = language):';
        foreach (['ar', 'en'] as $locale) {
            if (isset($written[$locale])) {
                $pending[$locale] = $written[$locale];
                $lines[] = "{$locale}: ".json_encode($written[$locale], JSON_UNESCAPED_UNICODE);
            } else {
                $other = $locale === 'ar' ? 'en' : 'ar';
                $pending[$locale] = $written[$other];
                $lines[] = "{$locale}: (empty - write it as a faithful translation of the \"{$other}\" text, laid out the same way)";
            }
        }

        $out = [];

        // A second try for a language whose answer lost something: models are
        // not deterministic, and a dropped number is worth one more go.
        for ($attempt = 0; $attempt < 2 && count($out) < count($pending); $attempt++) {
            $answers = $this->ai->json($this->systemPrompt(), implode("\n", $lines), 8192);

            foreach ($pending as $locale => $original) {
                if (isset($out[$locale])) {
                    continue;
                }

                $html = $answers[$locale] ?? null;
                $html = is_string($html) ? $this->sanitize($html) : '';

                // Text that came back empty, or in the wrong script, is no
                // improvement on what the admin already has.
                $arabic = (bool) preg_match('/\p{Arabic}/u', strip_tags($html));
                if (! $this->hasText($html) || ($locale === 'ar') !== $arabic) {
                    continue;
                }

                // Every number in the original (discounts, phones, prices,
                // hours) must still be there: a rewrite that loses one is
                // discarded rather than applied.
                if ($this->missingNumbers($original, $html) !== []) {
                    continue;
                }

                // The rewrite must be the admin's own text, laid out differently:
                // it may not lose their words, nor add a pile of its own.
                if (! $this->isFaithful($locale, $original, $html)) {
                    continue;
                }

                $out[$locale] = $html;
            }
        }

        return $out;
    }

    /**
     * Is $enhanced the same text as $original, just laid out differently?
     * Compared by words, and only when the original is written in the same
     * language as the rewrite (a translation shares no words — the numbers
     * check is what guards that case).
     */
    private function isFaithful(string $locale, string $original, string $enhanced): bool
    {
        $originalText = strip_tags($original);
        $sameScript = $locale === 'ar'
            ? (bool) preg_match('/\p{Arabic}/u', $originalText)
            : (! preg_match('/\p{Arabic}/u', $originalText) && preg_match('/\p{Latin}/u', $originalText));

        if (! $sameScript) {
            return true;
        }

        $before = $this->words($original);
        $after = $this->words($enhanced);

        if (count($before) < 3 || $after === []) {
            return true;
        }

        $beforeSet = array_flip($before);
        $afterSet = array_flip($after);

        $kept = count(array_filter($before, fn ($w) => isset($afterSet[$w])));
        $added = count(array_filter($after, fn ($w) => ! isset($beforeSet[$w])));

        // At least 85% of their words survive, and no more than a third of the
        // result is new (headings account for a few words).
        return $kept / count($before) >= 0.85 && $added / count($after) <= 0.34;
    }

    /**
     * Comparable words: tags and digits dropped, Arabic diacritics and letter
     * variants folded, the "ال" / "و" prefixes stripped, short words ignored.
     *
     * @return list<string>
     */
    private function words(string $html): array
    {
        // Tags become spaces: `</h3><ul><li>` would otherwise glue a heading to
        // the first word beneath it and read as one word nobody wrote.
        $text = mb_strtolower(html_entity_decode(preg_replace('/<[^>]*>/', ' ', $html) ?? $html));
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        preg_match_all('/[\p{L}]+/u', $text, $m);

        $words = [];
        foreach ($m[0] as $word) {
            if (mb_strlen($word) > 3 && str_starts_with($word, 'ال')) {
                $word = mb_substr($word, 2);
            }
            if (mb_strlen($word) > 3 && str_starts_with($word, 'و')) {
                $word = mb_substr($word, 1);
            }
            if (mb_strlen($word) >= 3) {
                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * The numbers in $original that do not appear in $enhanced. Arabic-Indic
     * digits count as their Western twins, and "1,000" as "1000".
     *
     * @return list<string>
     */
    private function missingNumbers(string $original, string $enhanced): array
    {
        $numbers = fn (string $html) => array_map(
            fn ($n) => ltrim($n, '0') === '' ? '0' : ltrim($n, '0'),
            $this->extractNumbers($html)
        );

        $have = array_count_values($numbers($enhanced));
        $missing = [];

        foreach ($numbers($original) as $number) {
            if (($have[$number] ?? 0) > 0) {
                $have[$number]--;
            } else {
                $missing[] = $number;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function extractNumbers(string $html): array
    {
        $text = html_entity_decode(strip_tags($html));
        // Arabic-Indic (٠-٩) and extended (۰-۹) digits to 0-9; drop thousands separators.
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $text = preg_replace('/(?<=\d)[,،٬](?=\d{3}\b)/u', '', $text) ?? $text;

        preg_match_all('/\d+(?:\.\d+)?/', $text, $m);

        return $m[0];
    }

    private function hasText(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html))) !== '' || str_contains($html, '<img');
    }

    /**
     * Only the layout tags survive, and only the attributes that carry content
     * (a link's href, an image's src / alt / size) — no styles, classes or
     * colours, so the text falls back to the site's default look.
     */
    private function sanitize(string $html): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS);

        return trim((string) preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function (array $m) {
            [$all, $slash, $tag, $attrs] = $m;
            $tag = strtolower($tag);

            if ($slash !== '') {
                return "</{$tag}>";
            }

            $keep = match ($tag) {
                'a' => ['href'],
                'img' => ['src', 'alt', 'width', 'height'],
                default => [],
            };

            $kept = '';
            foreach ($keep as $name) {
                if (preg_match('/\s'.$name.'\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $a)) {
                    $value = $a[2] !== '' ? $a[2] : ($a[3] ?? '');
                    // No javascript: links.
                    if (in_array($name, ['href', 'src'], true) && preg_match('/^\s*javascript:/i', $value)) {
                        continue;
                    }
                    $kept .= ' '.$name.'="'.htmlspecialchars(html_entity_decode($value), ENT_QUOTES).'"';
                }
            }

            return "<{$tag}{$kept}>";
        }, $html));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You reformat the description of a medical facility for an Egyptian medical
        directory. You change ONLY the layout. The words stay the admin's own.

        You get the description as HTML, one per language (keys "ar" and "en"). Return
        ONLY a JSON object with the same keys, each value the reformatted HTML. No other
        keys, no commentary, no code fences.

        What you may do:
        - Split the text into short lines or bullet points, and group related lines under
          a short <h3> heading that begins with ONE fitting emoji icon (🏷️ discounts,
          🩺 services, 📍 location, 🕒 hours, 📞 contact, 💳 payment ...). The heading
          only names what the lines beneath it already say (two to four words).
        - Put the key figures in <strong> (a discount percentage, a price, a phone number).
        - Fix an obvious typo or missing space. Nothing more.

        What you must NOT do:
        - Do NOT add anything: no introduction, no closing line, no extra sentence,
          service, benefit, adjective ("exclusive", "best", "leading", "professional"),
          claim or detail that is not in the original. If the original is three lines,
          the result is those three lines (split into bullets if they hold several items).
        - Do NOT summarise, shorten, paraphrase or reword. Reuse the original wording and
          spelling; each original line, its numbers and its terms must all still be there.
        - Do NOT drop, merge away or reorder facts in a way that changes their meaning.
        - Use only these tags: p, h3, ul, ol, li, strong, em, br, a, img. No styles,
          classes, colours or other attributes. Keep every <a> (with its href) and every
          <img> (with its src) exactly as given.

        Language rules:
        - Write each key in its own language ("ar" in Arabic, "en" in English). If the
          text under a key is in the other language (for example Arabic text sitting under
          "en"), give a faithful, complete translation of it — again adding nothing.
        - Always return BOTH keys. If a key's text is empty, write it as a faithful,
          complete translation of the other language's text, laid out the same way.
        PROMPT;
    }
}
