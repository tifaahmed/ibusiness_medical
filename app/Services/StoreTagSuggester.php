<?php

namespace App\Services;

use App\Enums\Tag\TagEnum;
use App\Models\Tag;
use App\Services\Ai\GeminiClient;

/**
 * Picks the tags that describe a store with Gemini. It first tries the tags
 * that already exist for stores; only when those do not cover the number the
 * admin asked for does it propose NEW tags (Arabic + English name, icon, color)
 * for the admin to accept one by one. Nothing is saved here.
 */
class StoreTagSuggester
{
    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  array<string, string>  $store  free-form facts about the store (title, descriptions, category ...)
     * @return array{matched: list<int>, suggestions: list<array{name: array{ar: string, en: string}, icon: string, color: string}>}
     */
    public function suggest(array $store, int $wanted): array
    {
        $existing = Tag::forPicker('stores');
        $byId = [];
        $catalogue = [];
        foreach ($existing as $tag) {
            $byId[$tag['id']] = $tag;
            $catalogue[] = ['id' => $tag['id'], 'ar' => $tag['name_translations']['ar'] ?? '', 'en' => $tag['name_translations']['en'] ?? ''];
        }

        $lines = [];
        foreach ($store as $label => $value) {
            $value = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $value))) ?? '');
            if ($value !== '') {
                $lines[] = "{$label}: ".mb_substr($value, 0, 1500);
            }
        }
        $lines[] = "Number of tags wanted: {$wanted}";
        $lines[] = 'Existing tags (JSON): '.json_encode($catalogue, JSON_UNESCAPED_UNICODE);

        $answer = $this->ai->json($this->systemPrompt(), implode("\n", $lines), 2048);

        // Only ids that really exist; no duplicates; never more than asked.
        $matched = [];
        foreach ((array) ($answer['matched'] ?? []) as $id) {
            if (is_numeric($id) && isset($byId[(int) $id]) && ! in_array((int) $id, $matched, true)) {
                $matched[] = (int) $id;
            }
        }
        $matched = array_slice($matched, 0, $wanted);

        $suggestions = [];
        if (count($matched) < $wanted) {
            $taken = collect($catalogue)->flatMap(fn ($c) => [$this->fold($c['ar']), $this->fold($c['en'])])->filter()->all();
            $icons = array_column(TagEnum::getIconOptions(), 'value');
            $colors = array_column(TagEnum::getColorOptions(), 'value');

            foreach ((array) ($answer['suggestions'] ?? []) as $row) {
                $ar = trim((string) ($row['ar'] ?? ''));
                $en = trim((string) ($row['en'] ?? ''));
                if ($ar === '' || $en === '' || mb_strlen($ar) > 60 || mb_strlen($en) > 60) {
                    continue;
                }
                if (in_array($this->fold($ar), $taken, true) || in_array($this->fold($en), $taken, true)) {
                    continue;
                }
                $taken[] = $this->fold($ar);
                $taken[] = $this->fold($en);

                $icon = (string) ($row['icon'] ?? '');
                $color = strtoupper((string) ($row['color'] ?? ''));
                $suggestions[] = [
                    'name' => ['ar' => $ar, 'en' => $en],
                    'icon' => in_array($icon, $icons, true) ? $icon : '🏷️',
                    'color' => in_array($color, $colors, true) ? $color : $colors[0],
                ];
            }
            $suggestions = array_slice($suggestions, 0, max($wanted * 2, 6));
        }

        return ['matched' => $matched, 'suggestions' => $suggestions];
    }

    private function fold(string $s): string
    {
        return mb_strtolower(trim($s));
    }

    private function systemPrompt(): string
    {
        $icons = implode(' ', array_column(TagEnum::getIconOptions(), 'value'));
        $colors = implode(' ', array_column(TagEnum::getColorOptions(), 'value'));

        return <<<PROMPT
        You choose tags for a store listed in an Egyptian medical directory/marketplace.
        You get facts about the store, how many tags are wanted, and the list of tags
        that already exist (id, Arabic name, English name).

        Return ONLY a JSON object, no commentary, no code fences:
        {"matched": [ids], "suggestions": [{"ar": "...", "en": "...", "icon": "...", "color": "..."}]}

        - "matched": ids of EXISTING tags that genuinely describe this store, best first,
          at most the number wanted. Include a tag only if it clearly fits; never pad.
        - "suggestions": only when fewer than the wanted number matched. NEW tags the
          store should have, short (one to three words), specific to what the store sells
          or offers, each with an Arabic and an English name that mean the same thing.
          Never repeat or paraphrase an existing tag. Propose about twice the missing
          number so the admin can choose. If enough existing tags matched, return [].
        - "icon" must be one of: {$icons}
        - "color" must be one of: {$colors}
        PROMPT;
    }
}
