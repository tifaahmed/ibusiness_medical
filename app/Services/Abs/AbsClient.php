<?php

namespace App\Services\Abs;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal ABS Courier & Freight Systems client.
 *
 * Deliberately thin and dependency-free — Laravel's HTTP client rather than an
 * SDK, the same shape as {@see \App\Services\Ai\GeminiClient}. It covers only
 * what the admin's "Ship with ABS" button needs: the two address dropdowns it
 * resolves a governorate and city against, and the call that books the
 * shipment.
 *
 * Every dropdown is asked in Arabic. Our orders store `customer_governorate`
 * and `customer_city` as free Arabic text the buyer typed, and matching those
 * against English labels would find nothing.
 *
 * @see https://docs.absegy.com — schema at https://docs.absegy.com/openapi.json
 */
class AbsClient
{
    /** Their dropdowns page at 20 by default; a governorate list is short. */
    private const DROPDOWN_LIMIT = 100;

    /**
     * Whether a key is configured. The order page hides the ship button when
     * not, rather than offering a button that can only fail.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.abs.key'));
    }

    /**
     * The governorates ABS delivers to, optionally narrowed by a search term.
     *
     * @return list<array{id: int, label: string}>
     *
     * @throws RuntimeException
     */
    public function governorates(?string $search = null): array
    {
        return $this->dropdown('/api/v1/shared/dropdown/governorates', array_filter([
            'search' => $search,
        ], fn ($value) => filled($value)));
    }

    /**
     * The cities of one governorate, optionally narrowed by a search term.
     *
     * @return list<array{id: int, label: string}>
     *
     * @throws RuntimeException
     */
    public function cities(int $governorateId, ?string $search = null): array
    {
        return $this->dropdown('/api/v1/shared/dropdown/cities', array_filter([
            'governorateId' => $governorateId,
            'search' => $search,
        ], fn ($value) => filled($value)));
    }

    /**
     * Book one shipment and return the AWB ABS generated for it.
     *
     * The AWB is the whole point of the call: it is the number the parcel is
     * tracked by from here on, and the only thing that proves the order was
     * actually handed over. A 2xx that carries no AWB is therefore treated as
     * a failure, not a success — an order marked shipped with nothing to track
     * is worse than one that visibly failed to ship.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     */
    public function createShipment(array $payload): string
    {
        $response = $this->request()->post($this->url('/api/v1/create-shipment'), $payload);

        if ($response->failed()) {
            /* The payload carries the customer's name, phone and address —
               summarise it rather than writing a buyer's details into the log
               on every courier hiccup. */
            Log::error('ABS shipment could not be created.', [
                'route' => '/api/v1/create-shipment',
                'status' => $response->status(),
                'ref' => $payload['shipment']['ref'] ?? null,
                'governorate_id' => $payload['shipment']['governorateId'] ?? null,
                'city_id' => $payload['shipment']['cityId'] ?? null,
                'response' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        $awb = $response->json('data');

        if (! is_string($awb) || blank($awb)) {
            Log::error('ABS accepted the shipment but returned no AWB.', [
                'route' => '/api/v1/create-shipment',
                'status' => $response->status(),
                'ref' => $payload['shipment']['ref'] ?? null,
                'response' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException(
                'ABS accepted the shipment but returned no AWB. Check the ABS portal before sending it again.'
            );
        }

        return $awb;
    }

    /**
     * One dropdown, flattened to `{id, label}` rows.
     *
     * ABS answers the paginated dropdowns with `data.content` and the flat ones
     * with `data`; this accepts either so a caller never has to care which.
     *
     * @param  array<string, mixed>  $query
     * @return list<array{id: int, label: string}>
     *
     * @throws RuntimeException
     */
    private function dropdown(string $path, array $query = []): array
    {
        $response = $this->request()->get($this->url($path), $query + [
            'lang' => 'ar',
            'limit' => self::DROPDOWN_LIMIT,
        ]);

        if ($response->failed()) {
            Log::error('ABS dropdown could not be read.', [
                'route' => $path,
                'status' => $response->status(),
                'query' => $query,
                'response' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException($this->errorMessage($response->json(), $response->status()));
        }

        $rows = $response->json('data.content') ?? $response->json('data') ?? [];

        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && isset($row['id']))
            ->map(fn (array $row) => [
                'id' => (int) $row['id'],
                'label' => (string) ($row['value'] ?? $row['name'] ?? $row['id']),
            ])
            ->values()
            ->all();
    }

    /**
     * @throws RuntimeException when no key is configured.
     */
    private function request(): PendingRequest
    {
        $key = config('services.abs.key');

        if (blank($key)) {
            throw new RuntimeException(
                'ABS_API_KEY is not set. Add it to your .env file to ship orders with ABS.'
            );
        }

        return Http::withHeaders(['x-api-key' => $key])
            ->timeout((int) config('services.abs.timeout', 30))
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.abs.base_url'), '/').$path;
    }

    /**
     * What went wrong, in words an admin can act on.
     *
     * ABS returns a validation failure as `message: string[]` and everything
     * else as a plain string, so both shapes are folded into one line. It is
     * shown in the ship dialog: "consigneeName should not be empty" tells the
     * admin which field to fix, where "Request failed" tells them nothing.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function errorMessage(?array $body, int $status): string
    {
        $message = $body['message'] ?? null;

        if (is_array($message)) {
            $message = implode(' ', array_map('strval', $message));
        }

        return filled($message)
            ? (string) $message
            : "ABS returned HTTP {$status}.";
    }
}
