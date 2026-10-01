<?php

namespace App\Services;

use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Log;

/**
 * Suggests the corner ribbon ("banner") of a facility card with Gemini: a short
 * Arabic and English message and the colours to paint it in, picked from what
 * the facility is (its name, type, discount and description).
 *
 * Nothing is saved here — the suggestion goes back into the open form for the
 * admin to look at in the live preview before saving. Size, angle and the
 * number of days stay the admin's: only the words and the colours are offered.
 */
class FacilityBannerSuggester
{
    /** The ribbon is one line that never wraps; longer text runs off the card. */
    public const MAX_MESSAGE_LENGTH = 24;

    /** Below this the text is hard to read on the ribbon (WCAG large-text AA is 3). */
    private const MIN_CONTRAST = 3.0;

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  array<string, string>  $context  labelled lines given to the model
     * @param  array{ar?: string|null, en?: string|null}  $current  the message on the form now, so a second press offers something new
     * @return array{message_ar: string, message_en: string, text_color: string, bg_color: string, shadow_color: string}|null
     *                                                                                                                         null when two answers were both unusable
     */
    public function suggest(array $context, array $current = []): ?array
    {
        $lines = [];
        foreach ($context as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        $currentAr = trim((string) ($current['ar'] ?? ''));
        $currentEn = trim((string) ($current['en'] ?? ''));
        if ($currentAr !== '' || $currentEn !== '') {
            $lines[] = '';
            $lines[] = "The banner currently says: \"{$currentAr}\" / \"{$currentEn}\". Suggest something different.";
        }

        $user = implode("\n", $lines);

        // Models are not deterministic: one more go for an answer that came back
        // too long, in the wrong script or with a colour that is not a colour.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $answer = $this->ai->json($this->systemPrompt(), $user, 512);
            $clean = $this->clean($answer);

            if ($clean !== null) {
                return $clean;
            }

            Log::warning('Facility banner suggestion rejected', [
                'attempt' => $attempt + 1,
                'answer' => mb_substr(json_encode($answer, JSON_UNESCAPED_UNICODE) ?: '', 0, 500),
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{message_ar: string, message_en: string, text_color: string, bg_color: string, shadow_color: string}|null
     */
    private function clean(array $answer): ?array
    {
        $ar = $this->message($answer['message_ar'] ?? null);
        $en = $this->message($answer['message_en'] ?? null);

        if ($ar === null || $en === null) {
            return null;
        }

        // Each message in its own language.
        if (! preg_match('/\p{Arabic}/u', $ar) || preg_match('/\p{Arabic}/u', $en) || ! preg_match('/\p{Latin}/u', $en)) {
            return null;
        }

        $bg = $this->color($answer['bg_color'] ?? null);
        if ($bg === null) {
            return null;
        }

        // A text colour the model got wrong, or one that disappears into the
        // ribbon, is replaced by whichever of white/black reads better on it.
        $text = $this->color($answer['text_color'] ?? null);
        if ($text === null || $this->contrast($text, $bg) < self::MIN_CONTRAST) {
            $text = $this->contrast('#ffffff', $bg) >= $this->contrast('#000000', $bg) ? '#ffffff' : '#000000';
        }

        $shadow = $this->color($answer['shadow_color'] ?? null) ?? '#00000033';

        return [
            'message_ar' => $ar,
            'message_en' => mb_strtoupper($en),
            // Stored as #rrggbbaa, the way the form's opacity sliders expect.
            'text_color' => $this->withAlpha($text, 'ff'),
            'bg_color' => $this->withAlpha($bg, 'ff'),
            'shadow_color' => $this->withAlpha($shadow, '33'),
        ];
    }

    private function message(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '', " \t\n\r\0\x0B\"'«»“”");

        if ($value === '' || mb_strlen($value) > self::MAX_MESSAGE_LENGTH) {
            return null;
        }

        return $value;
    }

    /** `#rgb`, `#rrggbb` or `#rrggbbaa`, lower-cased with the `#`; anything else is null. */
    private function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $hex = strtolower(ltrim(trim($value), '#'));

        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-f]{6}([0-9a-f]{2})?$/', $hex) ? '#'.$hex : null;
    }

    private function withAlpha(string $color, string $alpha): string
    {
        return strlen($color) === 9 ? $color : $color.$alpha;
    }

    /** WCAG contrast ratio of two colours, alpha ignored. */
    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function luminance(string $color): float
    {
        $channels = array_map(function (string $pair) {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($color, 1, 6), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function systemPrompt(): string
    {
        $max = self::MAX_MESSAGE_LENGTH;

        return <<<PROMPT
        You design the small diagonal corner ribbon ("banner") shown on a medical
        facility's card in an Egyptian medical discount-card directory. Members see it
        while browsing the list of hospitals, clinics, labs, pharmacies and so on.

        From what you are told about the facility, suggest ONE ribbon. Return ONLY a JSON
        object with exactly these keys, no commentary, no code fences:
          "message_ar":   the ribbon text in Arabic
          "message_en":   the same idea in English
          "bg_color":     the ribbon colour, hex "#rrggbb"
          "text_color":   the text colour, hex "#rrggbb", clearly readable on bg_color
          "shadow_color": a soft shadow, hex "#rrggbbaa" (a low alpha, e.g. "#00000033")

        The message:
        - Two or three words at most, and never more than {$max} characters per language.
          It is one line on a narrow ribbon.
        - Punchy and true. If a discount percentage is given, the strongest ribbon is
          usually that discount (e.g. "خصم 20%" / "20% OFF"). Otherwise use what the
          facility stands out for (new partner, a specialty, 24h service ...) — only if
          the information given supports it. Never invent a discount, a number or a
          claim that is not in the information.
        - No emoji, no quotes, no trailing punctuation.

        The colours:
        - Fit the facility's kind and mood: e.g. red/orange for a discount or offer,
          green/teal for pharmacies and labs, blue for hospitals and clinics, gold for
          premium. Saturated enough to stand out on a photo.
        - text_color must contrast strongly with bg_color (white on dark, near-black on
          light).
        PROMPT;
    }
}
