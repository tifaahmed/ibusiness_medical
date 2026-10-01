<?php

namespace App\Services;

use App\Enums\Tag\TagEnum;
use App\Models\Tag;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Log;

/**
 * Picks the tags that suit a facility with Gemini, out of the tags that exist.
 *
 * The model is handed the real tag ids and may only choose among them; any id
 * it makes up is dropped. When none of the existing tags fits it proposes ONE
 * new tag instead (Arabic + English name, an icon and a colour from the tag
 * form's own lists) — that proposal is only prefilled into the create popup,
 * never written here, so the admin decides whether the tag is worth having.
 */
class FacilityTagSuggester
{
    /** More than this and the chips stop meaning anything. */
    public const MAX_TAGS = 5;

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  array<string, string>  $context  labelled lines describing the facility
     * @param  list<int>  $selected  tag ids already ticked on the form
     * @return array{tag_ids: list<int>, new_tag: array{name: array{ar: string, en: string}, icon: string|null, color: string|null}|null}
     */
    public function suggest(array $context, array $selected = []): array
    {
        $tags = Tag::query()->get(['id', 'name']);
        $known = $tags->pluck('id')->map(fn ($id) => (int) $id)->all();

        $lines = [];
        foreach ($context as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        $lines[] = '';
        $lines[] = 'Existing tags (id: Arabic / English):';
        foreach ($tags as $tag) {
            $ar = $tag->getTranslation('name', 'ar', false);
            $en = $tag->getTranslation('name', 'en', false);
            $lines[] = "{$tag->id}: {$ar} / {$en}";
        }
        if ($tags->isEmpty()) {
            $lines[] = '(none yet)';
        }

        $selected = array_values(array_intersect(array_map('intval', $selected), $known));
        if ($selected !== []) {
            $lines[] = '';
            $lines[] = 'Already selected on this facility: '.implode(', ', $selected);
        }

        $answer = $this->ai->json($this->systemPrompt(), implode("\n", $lines), 1024);

        $ids = [];
        foreach ((array) ($answer['tag_ids'] ?? []) as $id) {
            if (is_numeric($id) && in_array((int) $id, $known, true) && ! in_array((int) $id, $ids, true)) {
                $ids[] = (int) $id;
            }
        }

        $invented = array_diff(array_map(fn ($id) => is_numeric($id) ? (int) $id : $id, (array) ($answer['tag_ids'] ?? [])), $known);
        if ($invented !== []) {
            Log::warning('Facility tag suggestion named tags that do not exist', ['ids' => array_values($invented)]);
        }

        return [
            'tag_ids' => array_slice($ids, 0, self::MAX_TAGS),
            'new_tag' => $this->newTag($answer['new_tag'] ?? null, $tags),
        ];
    }

    /**
     * The proposed tag, cleaned: both names present and in their own script,
     * the icon and colour taken from the tag form's lists (or left empty), and
     * dropped altogether when a tag by that name already exists.
     *
     * @param  \Illuminate\Support\Collection<int, Tag>  $tags
     * @return array{name: array{ar: string, en: string}, icon: string|null, color: string|null}|null
     */
    private function newTag(mixed $raw, $tags): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $ar = trim((string) ($raw['name_ar'] ?? ''));
        $en = trim((string) ($raw['name_en'] ?? ''));

        if ($ar === '' || $en === '' || mb_strlen($ar) > 40 || mb_strlen($en) > 40
            || ! preg_match('/\p{Arabic}/u', $ar) || preg_match('/\p{Arabic}/u', $en)) {
            return null;
        }

        $fold = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)) ?? $s);
        foreach ($tags as $tag) {
            if ($fold((string) $tag->getTranslation('name', 'ar', false)) === $fold($ar)
                || $fold((string) $tag->getTranslation('name', 'en', false)) === $fold($en)) {
                return null;
            }
        }

        $icons = array_column(TagEnum::getIconOptions(), 'value');
        $colors = array_column(TagEnum::getColorOptions(), 'value');

        $icon = (string) ($raw['icon'] ?? '');
        $color = strtoupper((string) ($raw['color'] ?? ''));

        return [
            'name' => ['ar' => $ar, 'en' => $en],
            'icon' => in_array($icon, $icons, true) ? $icon : null,
            'color' => in_array($color, $colors, true) ? $color : null,
        ];
    }

    private function systemPrompt(): string
    {
        $icons = implode(' ', array_column(TagEnum::getIconOptions(), 'value'));
        $colors = implode(', ', array_map(
            fn (array $c) => "{$c['value']} ({$c['label']})",
            TagEnum::getColorOptions()
        ));
        $max = self::MAX_TAGS;

        return <<<PROMPT
        You tag medical facilities (hospitals, clinics, labs, pharmacies, radiology
        centres, opticians ...) in an Egyptian medical discount-card directory. Tags are
        the filters members browse by.

        You get the facility's details and the list of EXISTING tags with their ids.
        Return ONLY a JSON object, no commentary, no code fences:
          {"tag_ids": [ ... ], "new_tag": null | {"name_ar": "...", "name_en": "...", "icon": "...", "color": "..."}}

        tag_ids:
        - The ids of the existing tags that genuinely describe THIS facility, best
          first, at most {$max}. Choose only from the ids listed — never invent one.
        - A tag fits only if the facility's details support it. Do not pick a tag just
          because it is popular or vaguely medical. An empty list is a fine answer.
        - Keep the ones already selected if they still fit.

        new_tag:
        - Only when NONE of the existing tags fits well (tag_ids empty, or only weak
          matches): propose the ONE tag that would best describe this facility, short
          (one to three words), useful as a filter for other facilities too (a
          specialty or a kind of service, not the facility's own name).
        - It must not duplicate an existing tag under another wording.
        - "icon": exactly one of: {$icons}
        - "color": exactly one of: {$colors}
        - Otherwise null.
        PROMPT;
    }
}
