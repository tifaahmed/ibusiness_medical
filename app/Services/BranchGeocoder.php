<?php

namespace App\Services;

use App\Services\Ai\GeminiClient;
use RuntimeException;

/**
 * Turns a branch's written address into coordinates and a Google Maps link.
 *
 * Backs the "Fill locations with AI" sweep on the facility list and the
 * per-branch button in the facility form's branch modal. The model is asked to
 * geocode the address it is given — nothing here calls a maps API, so treat the
 * result as a well-informed guess: it is written into latitude/longitude for an
 * admin to check on the map, not as surveyed truth. That is what `confidence`
 * is for, and why the sweep shows it per branch.
 *
 * The Maps URL is *not* asked of the model — a hallucinated link would look
 * perfectly valid and open the wrong place. It is built here from the
 * coordinates the model returned, so the link can never disagree with them.
 *
 * Shares the transport (and the free-tier rate-limit handling) with
 * {@see FacilitySeoGenerator}.
 */
class BranchGeocoder
{
    /**
     * Google's documented "search by coordinates" form. It opens the pin in the
     * Maps app on a phone and on maps.google.com otherwise, which is what an
     * admin clicking through from the branch list expects.
     */
    private const MAPS_URL = 'https://www.google.com/maps/search/?api=1&query=%s,%s';

    /**
     * How sure the model says it is. Anything else it invents is read as `low`.
     */
    public const CONFIDENCES = ['high', 'medium', 'low'];

    /**
     * Coordinates are stored as decimal(x,7); six places is ~11cm, which is far
     * beyond what this can honestly resolve, and keeps the column happy.
     */
    private const PRECISION = 6;

    public function __construct(private readonly GeminiClient $ai) {}

    /**
     * Whether a key is configured. The UI hides/disables the buttons when not.
     */
    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * Geocode one branch.
     *
     * @param  array{facility_name?: array|string|null, name?: array|string|null, address?: array|string|null, governorate?: string|null, city?: string|null, phone?: mixed}  $context
     * @return array{latitude: float|null, longitude: float|null, google_location_url: string|null, confidence: string, matched_place: string|null}
     *
     * @throws RuntimeException when the key is missing or the API call fails.
     * @throws \App\Services\Ai\RateLimitException when the provider is rate-limited.
     */
    public function locate(array $context): array
    {
        $decoded = $this->ai->json($this->systemPrompt(), $this->userPrompt($context), 512);

        return $this->normalize($decoded);
    }

    /**
     * The Maps link for a pair of coordinates, or null if either is missing.
     */
    public static function mapsUrl(float|string|null $latitude, float|string|null $longitude): ?string
    {
        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return null;
        }

        return sprintf(
            self::MAPS_URL,
            number_format((float) $latitude, self::PRECISION, '.', ''),
            number_format((float) $longitude, self::PRECISION, '.', '')
        );
    }

    /**
     * Whether an address carries enough detail to be worth an AI call at all.
     * A branch with nothing but a name would only get a city-centre guess.
     */
    public static function hasEnoughContext(array $context): bool
    {
        $address = self::flatten(data_get($context, 'address'));
        $city = trim((string) data_get($context, 'city'));
        $governorate = trim((string) data_get($context, 'governorate'));

        return $address !== '' || $city !== '' || $governorate !== '';
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a geocoder for Egyptian street addresses. You are given the written
        address of one branch of a medical facility and you return its coordinates.

        Respond with ONLY a JSON object in exactly this shape:
        {
          "latitude": 30.044400,
          "longitude": 31.235700,
          "confidence": "high" | "medium" | "low",
          "matched_place": "the place you located, in English"
        }

        Rules:
        - Coordinates are decimal degrees with 6 decimal places. Latitude is between
          -90 and 90, longitude between -180 and 180. Never return 0,0.
        - Locate the address as precisely as the text allows: a street and district
          if given, otherwise the district, otherwise the city centre.
        - "confidence": "high" when you recognise the specific street or landmark,
          "medium" when you place the district or neighbourhood, "low" when the best
          you can do is the city or governorate centre.
        - Use latitude and longitude null with confidence "low" when the address is
          too vague or contradictory to place at all. Guessing a random point is
          worse than returning null.
        - Almost every address is in Egypt. Arabic street names are common; read them
          as written and do not translate landmarks into a different city.
        - Do not return a URL, a plus code, or any commentary — only the JSON object.
        PROMPT;
    }

    private function userPrompt(array $context): string
    {
        $lines = [];

        $lines[] = 'Facility: '.(self::flatten(data_get($context, 'facility_name')) ?: '—');
        $lines[] = 'Branch name: '.(self::flatten(data_get($context, 'name')) ?: '—');
        $lines[] = 'Address: '.(self::flatten(data_get($context, 'address')) ?: '—');
        $lines[] = 'City: '.(trim((string) data_get($context, 'city')) ?: '—');
        $lines[] = 'Governorate: '.(trim((string) data_get($context, 'governorate')) ?: '—');
        $lines[] = 'Country: Egypt';

        return implode("\n", $lines);
    }

    /**
     * Flatten a translatable value to a single line the prompt can carry. Both
     * locales are handed over when they exist — the Arabic address is usually
     * the more detailed of the two, and the English disambiguates the city.
     */
    private static function flatten(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (! is_array($value)) {
            return '';
        }

        $parts = [];

        foreach (['ar', 'en'] as $locale) {
            $text = trim((string) ($value[$locale] ?? ''));

            if ($text !== '' && ! in_array($text, $parts, true)) {
                $parts[] = $text;
            }
        }

        return implode(' / ', $parts);
    }

    /**
     * Force the model's answer into coordinates this application can store, or
     * into nulls. Anything out of range, non-numeric, or sitting on Null Island
     * is a failed geocode rather than a value worth writing to a branch.
     *
     * @return array{latitude: float|null, longitude: float|null, google_location_url: string|null, confidence: string, matched_place: string|null}
     */
    private function normalize(array $decoded): array
    {
        $latitude = $this->coordinate(data_get($decoded, 'latitude'), 90);
        $longitude = $this->coordinate(data_get($decoded, 'longitude'), 180);

        // Null Island is what a model returns when it has nothing; it is a real
        // point in the Atlantic, so it has to be rejected explicitly.
        if ($latitude === null || $longitude === null || ($latitude === 0.0 && $longitude === 0.0)) {
            $latitude = null;
            $longitude = null;
        }

        $confidence = strtolower(trim((string) data_get($decoded, 'confidence')));
        $matched = data_get($decoded, 'matched_place');

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'google_location_url' => self::mapsUrl($latitude, $longitude),
            'confidence' => in_array($confidence, self::CONFIDENCES, true) ? $confidence : 'low',
            'matched_place' => is_string($matched) && trim($matched) !== '' ? mb_substr(trim($matched), 0, 190) : null,
        ];
    }

    private function coordinate(mixed $value, float $limit): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, self::PRECISION);

        return abs($number) <= $limit ? $number : null;
    }
}
