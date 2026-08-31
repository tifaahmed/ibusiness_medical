<?php

namespace App\Services\FacilityMigration;

use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Translates the English values an operator is editing on the migration preview
 * screen into Egyptian Arabic, so a spreadsheet that only ever filled the
 * English column can have its Arabic side populated before the import runs.
 *
 * Stateless and row-agnostic on purpose: it is handed a flat list of strings
 * (each tagged with the kind of field it came from) and returns the Arabic for
 * each, in the same order. The preview screen decides which rows to send and
 * where to write the answers back.
 */
class MigrationTextTranslator
{
    /** Most strings accepted in one call — keeps the prompt and reply bounded. */
    public const MAX_ITEMS = 50;

    /** The model's raw decoded answer from the last call — for diagnostics. */
    public mixed $lastAnswer = null;

    public function __construct(private readonly GeminiClient $ai) {}

    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * @param  list<array{text: string, kind?: string}>  $items
     * @return list<string> Arabic per input index; '' where the model gave nothing usable
     *
     * @throws \App\Services\Ai\RateLimitException on HTTP 429
     * @throws RuntimeException when the key is missing or the call fails
     */
    public function toArabic(array $items): array
    {
        $items = array_values($items);
        if ($items === []) {
            return [];
        }
        if (count($items) > self::MAX_ITEMS) {
            throw new RuntimeException('Too many values in one translation request.');
        }

        $answers = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($items),
            4096,
        );

        $this->lastAnswer = $answers;
        $map = $this->indexedAnswers($answers, count($items));

        $out = [];
        foreach ($items as $index => $item) {
            $value = $map[$index] ?? null;
            $out[$index] = is_scalar($value) ? trim((string) $value) : '';
        }

        if (implode('', $out) === '') {
            // Nothing usable came back — record the shape so it can be diagnosed
            // rather than the buttons just silently doing nothing.
            Log::warning('Migration translate: empty result', [
                'sent' => count($items),
                'answer_keys' => is_array($answers) ? array_slice(array_keys($answers), 0, 10) : gettype($answers),
                'answer_sample' => mb_substr(json_encode($answers, JSON_UNESCAPED_UNICODE), 0, 500),
            ]);
        }

        return $out;
    }

    /**
     * Pull one Arabic string per position out of whatever shape the model chose:
     * `{"0":"…","1":"…"}`, a bare list `["…","…"]`, a single wrapper key holding
     * either of those (`{"translations":[…]}`), or a list of `{text|arabic|ar|value}`
     * objects.
     *
     * @param  mixed  $answers
     * @return array<int, string>
     */
    private function indexedAnswers($answers, int $count): array
    {
        if (! is_array($answers)) {
            return [];
        }

        // One wrapper key around the real payload.
        if (count($answers) === 1) {
            $inner = reset($answers);
            if (is_array($inner)) {
                $answers = $inner;
            }
        }

        $out = [];
        $i = 0;
        foreach ($answers as $key => $value) {
            if (is_array($value)) {
                $value = $value['arabic'] ?? $value['ar'] ?? $value['text'] ?? $value['value'] ?? '';
            }
            $position = is_numeric($key) ? (int) $key : $i;
            $out[$position] = is_scalar($value) ? (string) $value : '';
            $i++;
        }

        return $out;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You localise English data for an Egyptian medical directory into Arabic as
        it is written in Egypt.

        You are given a numbered list of values, each tagged with the kind of field
        it is. Return ONLY a JSON object mapping each number (as a string key) to
        the Arabic string. No other keys, no commentary, no transliteration of the
        whole phrase back to Latin letters.

        Rules by field type:
        - name (facility or branch name): translate the descriptor
          (Hospital -> مستشفى, Clinic -> عيادة, Center -> مركز, Lab -> معمل,
          Pharmacy -> صيدلية, Dr. -> د.) and render proper nouns in their common
          Egyptian Arabic spelling. No trailing punctuation.
        - address: a natural Arabic street address. Translate generic words
          (Street -> شارع, Square -> ميدان, Tower -> برج, Floor -> الدور) and keep
          building and flat numbers as digits.
        - text: faithful Arabic prose; keep the meaning and tone, add nothing.

        If a value is already Arabic, return it unchanged. Never invent
        information that is not in the English source.
        PROMPT;
    }

    /**
     * @param  list<array{text: string, kind?: string}>  $items
     */
    private function userPrompt(array $items): string
    {
        $lines = ['Values to translate to Arabic:'];

        foreach ($items as $index => $item) {
            $kind = in_array($item['kind'] ?? 'text', ['name', 'address', 'text'], true)
                ? $item['kind']
                : 'text';
            $text = trim((string) ($item['text'] ?? ''));
            $lines[] = "{$index}. [{$kind}] {$text}";
        }

        return implode("\n", $lines);
    }
}
