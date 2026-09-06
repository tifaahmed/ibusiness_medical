<?php

namespace App\Http\Requests\Concerns;

use App\Models\FacilityBranch;
use App\Support\PhoneNumbers;
use Illuminate\Validation\Rule;

/**
 * Shared handling of the phone list a branch is saved with.
 *
 * The branch form posts one entry per number, each with the kind of line it is
 * ({"number": "0663400006", "type": "landline"}), but the same endpoints still
 * receive flat strings from the spreadsheet importer and from anything written
 * against the older shape. Normalising before the rules run means validation
 * only has to describe the one shape, and a caller that sends the old one gets
 * its numbers typed by {@see PhoneNumbers::guessType()} rather than rejected.
 */
trait NormalisesBranchPhones
{
    /**
     * The rules for a normalised phone list.
     *
     * @param  string  $prefix  'phone', or 'branches.*.phone' on the full
     *                          facility form where branches are posted as a list.
     * @return array<string, mixed>
     */
    protected function phoneRules(string $prefix = 'phone'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            $prefix.'.*' => ['array'],
            // 20 characters is what a single normalised number fits in; the
            // splitter has already pulled packed cells apart by this point.
            $prefix.'.*.number' => ['required', 'string', 'max:'.PhoneNumbers::MAX_LENGTH],
            $prefix.'.*.type' => ['required', Rule::in(FacilityBranch::PHONE_TYPES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function phoneMessages(string $prefix = 'phone'): array
    {
        return [
            $prefix.'.*.number.required' => 'Enter the phone number, or remove the empty row.',
            $prefix.'.*.number.max' => 'Each phone number must be '.PhoneNumbers::MAX_LENGTH.' characters or fewer.',
            $prefix.'.*.type.required' => 'Choose what kind of number this is.',
            $prefix.'.*.type.in' => 'Choose a phone type from the list.',
        ];
    }

    /**
     * Turn whatever was posted into typed entries, dropping the blank rows an
     * admin leaves behind after clicking "add" once too often.
     *
     * @return list<array{number: string, type: string}>
     */
    protected function normalisedPhones(mixed $raw): array
    {
        if (! is_array($raw) && ! is_string($raw)) {
            return [];
        }

        return PhoneNumbers::entries($raw);
    }
}
