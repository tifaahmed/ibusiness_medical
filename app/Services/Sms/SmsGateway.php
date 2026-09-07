<?php

namespace App\Services\Sms;

use App\Support\PhoneNumbers;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends one SMS through Advance Messaging Systems' GWE2S HTTP API.
 *
 * The whole contract is a single GET — `websms?user=&pass=&sid=&mno=&type=&text=`
 * — answered either with a message id or with the string `ERROR - HTTPnn`. There
 * is no status code to read: the gateway returns 200 for a rejected message just
 * as readily as for an accepted one, so {@see send()} decides from the BODY.
 *
 * Nothing here retries. The one thing this sends today is a login code somebody
 * is waiting on, and a second attempt at a gateway that has just refused the
 * first costs the visitor another timeout for the same answer.
 *
 * @see config/services.php ('sms') for the credentials
 */
class SmsGateway
{
    /** Latin text, 160 characters to the message. */
    public const TYPE_ENGLISH = 1;

    /** Unicode, given as UTF-16 code points — not what this sends. */
    public const TYPE_UNICODE = 2;

    /** Latin text carrying $ or @, 160 characters. */
    public const TYPE_SPECIAL = 3;

    /** Arabic, 70 characters to the message. */
    public const TYPE_ARABIC = 4;

    /**
     * Characters the gateway refuses inside `text`, per the API document. They
     * are stripped rather than escaped: a login message has no use for either,
     * and a rejected message is worse than a message missing an ampersand.
     */
    private const FORBIDDEN_CHARACTERS = ['&', '#'];

    /**
     * Whether the gateway has enough configuration to send anything.
     *
     * A sender id plus either an access key or a user/password pair. Read by
     * `OtpSettings::deliversBySms()`, so an install with no credentials falls
     * back to the fixed code instead of asking members for a code that could
     * never arrive.
     */
    public static function isConfigured(): bool
    {
        $config = config('services.sms');

        $sender = trim((string) ($config['sender'] ?? ''));

        if ($sender === '') {
            return false;
        }

        $hasAccessKey = trim((string) ($config['access_key'] ?? '')) !== '';
        $hasCredentials = trim((string) ($config['user'] ?? '')) !== ''
            && trim((string) ($config['password'] ?? '')) !== '';

        return $hasAccessKey || $hasCredentials;
    }

    /**
     * Send `$message` to `$phone`, and say whether the gateway took it.
     *
     * @param  string  $phone  as stored here (01xxxxxxxxx) or already
     *                         internationalised — {@see msisdn()}
     * @return array{sent: bool, reference: ?string, error: ?string}
     */
    public function send(string $phone, string $message): array
    {
        if (! self::isConfigured()) {
            return $this->failure('The SMS gateway is not configured.');
        }

        $config = config('services.sms');

        $destination = $this->msisdn($phone);

        if ($destination === null) {
            return $this->failure('The phone number is not one this can send to.');
        }

        $text = $this->clean($message);

        $query = array_filter([
            'accesskey' => trim((string) ($config['access_key'] ?? '')) ?: null,
            'user' => trim((string) ($config['access_key'] ?? '')) === ''
                ? (string) ($config['user'] ?? '')
                : null,
            'pass' => trim((string) ($config['access_key'] ?? '')) === ''
                ? (string) ($config['password'] ?? '')
                : null,
            'sid' => (string) ($config['sender'] ?? ''),
            'mno' => $destination,
            'type' => (string) $this->typeFor($text),
            'text' => $text,
            /*
             * Ask for JSON so a success is `{"Response":["1912181513205684021"]}`
             * rather than a bare id. Errors come back as plain text either way,
             * which is why `readResponse()` reads both shapes.
             */
            'respformat' => 'json',
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::withOptions(['verify' => (bool) ($config['verify'] ?? true)])
                ->timeout((int) ($config['timeout'] ?? 15))
                ->get((string) $config['url'], $query);
        } catch (ConnectionException $exception) {
            Log::warning('SMS gateway unreachable.', [
                'destination' => $destination,
                'message' => $exception->getMessage(),
            ]);

            return $this->failure('Could not reach the SMS gateway.');
        }

        if ($response->failed()) {
            Log::warning('SMS gateway answered with an error status.', [
                'destination' => $destination,
                'status' => $response->status(),
            ]);

            return $this->failure("The SMS gateway answered with {$response->status()}.");
        }

        return $this->readResponse($response->body(), $destination);
    }

    /**
     * The gateway's answer as a result.
     *
     * A submitted message is a message id — a long run of digits — either bare
     * or wrapped in `{"Response":[...]}`. Anything else is a refusal, and the
     * `ERROR - HTTPnn` codes are listed in the API document (HTTP04 invalid
     * password, HTTP06 invalid sender id, HTTP18 out of credit, and so on).
     *
     * @return array{sent: bool, reference: ?string, error: ?string}
     */
    private function readResponse(string $body, string $destination): array
    {
        $body = trim($body);

        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $first = $decoded['Response'][0] ?? null;
            $body = is_scalar($first) ? trim((string) $first) : $body;
        }

        if (preg_match('/^\d{6,}$/', $body) === 1) {
            return [
                'sent' => true,
                'reference' => $body,
                'error' => null,
            ];
        }

        Log::warning('SMS gateway refused a message.', [
            'destination' => $destination,
            'response' => $body,
        ]);

        return $this->failure($body === '' ? 'The SMS gateway gave no answer.' : $body);
    }

    /**
     * A stored phone number as the gateway wants it: digits only, country code
     * on the front, no plus.
     *
     * Arabic-Indic digits are folded first — a member's row can carry them, and
     * `٠١٠…` is a perfectly good phone number that no gateway would accept.
     * Returns null for anything too short to be a real number, so a truncated
     * row fails here rather than sending a code into the void.
     */
    public function msisdn(string $phone): ?string
    {
        $code = preg_replace('/\D+/', '', (string) config('services.sms.country_code', '20')) ?? '';

        $digits = preg_replace('/\D+/', '', PhoneNumbers::foldDigits($phone)) ?? '';

        // 0020… and 0020… both mean the same thing as 20….
        $digits = preg_replace('/^00+/', '', $digits) ?? $digits;

        if ($code !== '' && str_starts_with($digits, $code.'0')) {
            // "2001020709993" — a country code in front of a national number
            // that kept its trunk zero. Drop the zero, not the code.
            $digits = $code.substr($digits, strlen($code) + 1);
        } elseif ($code !== '' && ! str_starts_with($digits, $code)) {
            $digits = $code.ltrim($digits, '0');
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    /**
     * Which of the gateway's four message types `$text` needs.
     *
     * Only two are ever chosen: Arabic (70 characters) when the text contains
     * an Arabic letter, Latin (160) otherwise. Unicode is not used — it wants
     * the body given as UTF-16 code points, and nothing here writes one.
     */
    private function typeFor(string $text): int
    {
        return preg_match('/\p{Arabic}/u', $text) === 1
            ? self::TYPE_ARABIC
            : self::TYPE_ENGLISH;
    }

    /**
     * The message with the characters the gateway rejects taken out.
     */
    private function clean(string $message): string
    {
        return trim(str_replace(self::FORBIDDEN_CHARACTERS, '', $message));
    }

    /**
     * @return array{sent: bool, reference: null, error: string}
     */
    private function failure(string $error): array
    {
        return [
            'sent' => false,
            'reference' => null,
            'error' => $error,
        ];
    }
}
