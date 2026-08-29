<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal Google Gemini (Generative Language API) client.
 *
 * Deliberately thin and dependency-free — Laravel's HTTP client rather than an
 * SDK. Callers hand it a system prompt and a user prompt and get a decoded JSON
 * object back; the request pins `responseMimeType` to application/json so the
 * model answers with one object. Shared by {@see \App\Services\ProductSeoGenerator},
 * {@see \App\Services\FacilitySeoGenerator}, {@see \App\Services\ProductEnglishBackfiller}
 * and {@see \App\Services\FacilityEnglishBackfiller}.
 */
class GeminiClient
{
    /**
     * Whether a key is configured. The UI hides/disables the AI buttons when not.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * Send one prompt and decode the model's JSON answer.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the key is missing or the API call fails.
     */
    public function json(string $system, string $user, int $maxTokens = 1024): array
    {
        $key = config('services.gemini.key');

        if (blank($key)) {
            throw new RuntimeException('GEMINI_API_KEY is not set. Add it to your .env file to use AI generation.');
        }

        $model = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $base = rtrim((string) config('services.gemini.base_url'), '/');

        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->timeout((int) config('services.gemini.timeout', 45))
            ->acceptJson()
            ->post("{$base}/models/{$model}:generateContent", [
                'systemInstruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $user]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.6,
                    'maxOutputTokens' => $maxTokens,
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($response->failed()) {
            Log::error('Gemini request failed', [
                'status' => $response->status(),
                // The body carries Google's error reason/message, never the key.
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            if ($response->status() === 429) {
                throw new RateLimitException($this->friendlyError(429));
            }

            throw new RuntimeException($this->friendlyError($response->status()));
        }

        $content = data_get($response->json(), 'candidates.0.content.parts.0.text');
        $decoded = is_string($content) ? json_decode($this->unwrap($content), true) : null;

        if (! is_array($decoded)) {
            Log::error('Gemini returned unparseable content', [
                'finish_reason' => data_get($response->json(), 'candidates.0.finishReason'),
                'content' => is_string($content) ? mb_substr($content, 0, 500) : gettype($content),
            ]);

            throw new RuntimeException('The AI response could not be read. Please try again.');
        }

        return $decoded;
    }

    /**
     * The response mime type should keep this clean, but a stray ```json fence
     * is cheap to peel off just in case.
     */
    private function unwrap(string $text): string
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        }

        return trim($text);
    }

    private function friendlyError(int $status): string
    {
        return match (true) {
            $status === 400 => 'Gemini rejected the request. Check the API key and model name in your .env file.',
            $status === 401, $status === 403 => 'Gemini rejected the API key. Check GEMINI_API_KEY in your .env file.',
            $status === 429 => 'Gemini rate limit or quota reached. Try again shortly.',
            $status >= 500 => 'Gemini is temporarily unavailable. Please try again.',
            default => 'The AI request failed. Please try again.',
        };
    }
}
