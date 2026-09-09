<?php

namespace App\Services;

use App\Models\City;
use App\Models\Governorate;
use App\Services\Ai\GeminiClient;
use RuntimeException;

/**
 * Reads a branch's written address and says which governorate and city it is in.
 *
 * Backs the "Fill governorate & city from the address" button on the branch
 * form. The two fields are required to save a branch, and an address almost
 * always names the place already — so this is the same edit an admin would make
 * by hand after reading their own address back to themselves.
 *
 * The model is never asked to *name* the place: it is handed the governorates
 * and cities that actually exist in this database, each with its id, and asked
 * to pick one. Anything it answers that is not in that list, or a city that
 * does not sit in the governorate it chose, is discarded here — so a confident
 * hallucination cannot write a place that is not real, or an impossible pair.
 *
 * Nothing is saved: the ids land in the open form for the admin to check, as
 * with {@see BranchGeocoder} and the "Fix English with AI" button.
 */
class BranchPlaceResolver
{
    /** How sure the model says it is. Anything else it invents is read as `low`. */
    public const CONFIDENCES = ['high', 'medium', 'low'];

    public function __construct(private readonly GeminiClient $ai) {}

    /**
     * Whether a key is configured. The UI disables the button when not.
     */
    public static function isConfigured(): bool
    {
        return GeminiClient::isConfigured();
    }

    /**
     * An address with nothing in it can only ever produce a guess, so the
     * button stays dead rather than spending a call to be told nothing.
     */
    public static function hasEnoughContext(array $context): bool
    {
        return self::flatten(data_get($context, 'address')) !== ''
            || self::flatten(data_get($context, 'name')) !== '';
    }

    /**
     * @param  array{name?: array|string|null, address?: array|string|null, facility_name?: array|string|null}  $context
     * @return array{governorate_id: int|null, city_id: int|null, confidence: string, matched_place: string|null}
     *
     * @throws RuntimeException when the key is missing or the API call fails.
     */
    public function resolve(array $context): array
    {
        $catalogue = $this->catalogue();

        if ($catalogue['lines'] === '') {
            throw new RuntimeException('There are no governorates or cities set up yet, so there is nothing to choose from.');
        }

        $decoded = $this->ai->json(
            $this->systemPrompt(),
            $this->userPrompt($context, $catalogue['lines']),
            512
        );

        return $this->normalize($decoded, $catalogue['cities']);
    }

    /**
     * Every governorate with the cities inside it, as the prompt's numbered
     * list, plus the city→governorate map used to check the answer.
     *
     * @return array{lines: string, cities: array<int, int>}
     */
    private function catalogue(): array
    {
        $cities = City::query()
            ->select(['id', 'governorate_id', 'name'])
            ->get()
            ->groupBy('governorate_id');

        $lines = [];
        $cityOwners = [];

        foreach (Governorate::query()->select(['id', 'name'])->orderBy('id')->get() as $governorate) {
            $lines[] = sprintf('G%d: %s', $governorate->id, self::bothNames($governorate));

            foreach ($cities->get($governorate->id, collect()) as $city) {
                $lines[] = sprintf('  C%d: %s', $city->id, self::bothNames($city));
                $cityOwners[(int) $city->id] = (int) $governorate->id;
            }
        }

        return ['lines' => implode("\n", $lines), 'cities' => $cityOwners];
    }

    /** "Arabic / English", or whichever of the two the row carries. */
    private static function bothNames(Governorate|City $row): string
    {
        $names = array_filter([
            trim((string) $row->getTranslation('name', 'ar', false)),
            trim((string) $row->getTranslation('name', 'en', false)),
        ], fn ($name) => $name !== '');

        return implode(' / ', array_unique($names)) ?: '—';
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You place an Egyptian medical facility branch into the governorate and city
        it belongs to, choosing ONLY from a list you are given.

        Respond with ONLY a JSON object in exactly this shape:
        {
          "governorate_id": 1,
          "city_id": 12,
          "confidence": "high" | "medium" | "low",
          "matched_place": "the place you chose, in English"
        }

        Rules:
        - The ids MUST come from the list in the message. "G12" means governorate_id
          12; "C34" means city_id 34. Never invent an id, and never return a name in
          place of an id.
        - The city you choose MUST be one of the cities listed underneath the
          governorate you choose. If the address names a city you cannot find in the
          list, choose the governorate alone and return city_id null.
        - Read the Arabic address as written. Districts and landmarks are usually the
          strongest clue: "المعادي" is a district of Cairo, "سيدي جابر" of Alexandria.
        - "confidence": "high" when the address names the city or a district you know
          belongs to it, "medium" when you infer it from a landmark or street,
          "low" when you are mostly guessing from the governorate alone.
        - Return null for anything the address does not support. A wrong place is
          worse than an empty one — an admin can read a blank field, but will not
          re-check one that is confidently filled in.
        - No commentary, no explanation, only the JSON object.
        PROMPT;
    }

    private function userPrompt(array $context, string $catalogue): string
    {
        $lines = [
            'Facility: '.(self::flatten(data_get($context, 'facility_name')) ?: '—'),
            'Branch name: '.(self::flatten(data_get($context, 'name')) ?: '—'),
            'Address: '.(self::flatten(data_get($context, 'address')) ?: '—'),
            '',
            'Choose from these governorates (G) and their cities (C):',
            $catalogue,
        ];

        return implode("\n", $lines);
    }

    /**
     * Flatten a translatable value to one line. Both locales go over when they
     * exist — the Arabic address is usually the more detailed of the two.
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
     * Keep only an answer that describes a real, self-consistent place.
     *
     * @param  array<int, int>  $cityOwners  city id => governorate id
     * @return array{governorate_id: int|null, city_id: int|null, confidence: string, matched_place: string|null}
     */
    private function normalize(array $decoded, array $cityOwners): array
    {
        $governorateId = $this->id(data_get($decoded, 'governorate_id'));
        $cityId = $this->id(data_get($decoded, 'city_id'));

        // A city that is not in the list is not a city we have.
        if ($cityId !== null && ! isset($cityOwners[$cityId])) {
            $cityId = null;
        }

        if ($cityId !== null) {
            // The city is the more specific answer, so when the two disagree the
            // governorate is corrected to the one the city actually sits in
            // rather than the pair being thrown away.
            $governorateId = $cityOwners[$cityId];
        } elseif ($governorateId !== null && ! Governorate::whereKey($governorateId)->exists()) {
            $governorateId = null;
        }

        $confidence = strtolower(trim((string) data_get($decoded, 'confidence')));
        $matched = data_get($decoded, 'matched_place');

        return [
            'governorate_id' => $governorateId,
            'city_id' => $cityId,
            'confidence' => in_array($confidence, self::CONFIDENCES, true) ? $confidence : 'low',
            'matched_place' => is_string($matched) && trim($matched) !== '' ? mb_substr(trim($matched), 0, 190) : null,
        ];
    }

    private function id(mixed $value): ?int
    {
        if (is_string($value)) {
            // "G12" / "C34" come back now and then despite the prompt.
            $value = ltrim(trim($value), 'GCgc');
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
