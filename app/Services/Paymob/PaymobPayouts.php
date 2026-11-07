<?php

namespace App\Services\Paymob;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paymob Payouts (instant cash-in to a Vodafone Cash wallet). Thin and defensive: it never throws for a payment outcome, it RETURNS one of
 *
 *   success  — Paymob says the money was deposited
 *   pending  — accepted, final status comes later (ask `inquire`)
 *   failed   — Paymob definitely did NOT move money (validation, not eligible, over limit, no budget …)
 *   not_sent — we could not even log in to Paymob, so nothing was sent (safe to give the money back)
 *   unknown  — we cannot tell (timeout, 5xx, garbage). NEVER treat as failed: money may have left. Ask `inquire` with the same reference.
 *
 * The access token (valid 60 min) is cached and refreshed; a 401 refreshes once and retries — safe, because a 401 means nothing was processed.
 * Credentials are never logged.
 */
class PaymobPayouts
{
    private const TOKEN_KEY = 'paymob.payouts.token';

    public function configured(): bool
    {
        foreach (['base_url', 'client_id', 'client_secret', 'username', 'password'] as $key) {
            if (! filled(config('services.paymob_payouts.'.$key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{outcome: string, transaction_id: ?string, status: ?string, code: ?string, message: ?string}
     */
    public function disburse(string $msisdn, float $amount, string $nationalId, string $clientReference, ?string $comment = null): array
    {
        $payload = [
            'issuer' => 'vodafone',
            'amount' => number_format($amount, 2, '.', ''),
            'msisdn' => $msisdn,
            'national_id' => $nationalId,
            'client_reference_id' => $clientReference,
            'customer_bears_fees' => false,
        ] + ($comment ? ['comment' => $comment] : []);

        return $this->call('post', '/api/secure/disburse/', $payload);
    }

    /** Where a payout stands, by the reference we sent. Returns the same shape as `disburse`. */
    public function inquire(string $clientReference): array
    {
        $result = $this->call('get', '/api/secure/transaction/inquire/', ['transactions_ids_list' => [$clientReference]], inquiry: true);

        return $result;
    }

    /** The merchant budget left (EGP), or null when it cannot be read (5 calls a minute at most). */
    public function budget(): ?float
    {
        try {
            $response = $this->request()->get($this->url('/api/secure/budget/inquire/'));

            return preg_match('/([\d.,]+)\s*LE/i', (string) $response->json('current_budget'), $m) ? (float) str_replace(',', '', $m[1]) : null;
        } catch (Throwable $e) {
            Log::error('Paymob payouts budget could not be read.', ['message' => $e->getMessage()]);

            return null;
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.paymob_payouts.base_url'), '/').$path;
    }

    private function token(bool $fresh = false): string
    {
        if ($fresh) {
            Cache::forget(self::TOKEN_KEY);
        }

        return Cache::remember(self::TOKEN_KEY, now()->addMinutes(55), function () {
            $cfg = config('services.paymob_payouts');
            $response = Http::asForm()->withBasicAuth((string) $cfg['client_id'], (string) $cfg['client_secret'])->timeout(20)
                ->post($this->url('/api/secure/o/token/'), ['grant_type' => 'password', 'username' => $cfg['username'], 'password' => $cfg['password']]);

            if (! $response->successful() || ! filled($response->json('access_token'))) {
                throw new PayoutLoginFailed('Paymob payouts login failed (HTTP '.$response->status().').');
            }

            return (string) $response->json('access_token');
        });
    }

    private function request(bool $fresh = false): PendingRequest
    {
        return Http::withToken($this->token($fresh))->acceptJson()->asJson()->timeout(30);
    }

    private function call(string $method, string $path, array $payload, bool $inquiry = false): array
    {
        try {
            $response = $this->send($method, $path, $payload, false);
            if ($response->status() === 401) {
                $response = $this->send($method, $path, $payload, true);
            }
        } catch (PayoutLoginFailed $e) {
            Log::error('Paymob payouts: login failed.', ['path' => $path, 'message' => $e->getMessage()]);

            return $this->result('not_sent', null, null, null, 'Could not log in to Paymob Payouts.');
        } catch (ConnectionException $e) {
            Log::error('Paymob payouts: no answer.', ['path' => $path, 'message' => $e->getMessage()]);

            return $this->result('unknown', null, null, null, 'No answer from Paymob.');
        } catch (Throwable $e) {
            Log::error('Paymob payouts call failed.', ['path' => $path, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return $this->result('unknown', null, null, null, 'Payout call failed.');
        }

        return $inquiry ? $this->readInquiry($response) : $this->read($response);
    }

    private function send(string $method, string $path, array $payload, bool $fresh): Response
    {
        $pending = $this->request($fresh);

        return $method === 'get' ? $pending->send('GET', $this->url($path), ['json' => $payload]) : $pending->post($this->url($path), $payload);
    }

    private function read(Response $response): array
    {
        return $this->interpret((array) $response->json(), $response->status());
    }

    private function interpret(array $json, int $http): array
    {
        $status = strtolower((string) ($json['disbursement_status'] ?? ''));
        $code = isset($json['status_code']) ? (string) $json['status_code'] : null;
        $message = $json['status_description'] ?? null;
        $message = is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : ($message !== null ? (string) $message : null);
        $id = isset($json['transaction_id']) ? (string) $json['transaction_id'] : null;

        if (in_array($status, ['success', 'successful'], true)) {
            return $this->result('success', $id, $status, $code, $message);
        }

        if ($status === 'pending') {
            return $this->result('pending', $id, $status, $code, $message);
        }

        /* A definite refusal: Paymob answered "failed", or rejected the request itself (4xx) before doing anything. */
        if ($status === 'failed' || in_array($http, [400, 403, 406], true)) {
            return $this->result('failed', $id, $status ?: 'failed', $code ?? (string) $http, $message);
        }

        /* 401 after a refresh, 429, 5xx, an empty body …: we do not know. */
        return $this->result('unknown', $id, $status ?: null, $code ?? (string) $http, $message);
    }

    private function readInquiry(Response $response): array
    {
        if (! $response->successful()) {
            return $this->result('unknown', null, null, (string) $response->status(), null);
        }

        $row = $response->json('results.0');
        if (! is_array($row)) {
            return $this->result('not_found', null, null, null, 'Paymob has no record of this reference.');
        }

        return $this->interpret($row, 200);
    }

    private function result(string $outcome, ?string $id, ?string $status, ?string $code, ?string $message): array
    {
        return ['outcome' => $outcome, 'transaction_id' => $id, 'status' => $status, 'code' => $code, 'message' => $message !== null ? mb_substr($message, 0, 500) : null];
    }
}
