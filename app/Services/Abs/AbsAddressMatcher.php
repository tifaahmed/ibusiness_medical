<?php

namespace App\Services\Abs;

/**
 * Matches the free Arabic text an order carries against an ABS dropdown.
 *
 * `orders.customer_governorate` and `customer_city` are whatever the buyer
 * typed on the storefront — "الجيزة", "الجيزه", "السادس من أكتوبر". ABS wants
 * a numeric id. Somebody has to bridge the two, and doing it by eye for every
 * order is exactly the re-typing this feature exists to remove.
 *
 * So this narrows the list to a best guess and stops there. It never picks on
 * the strength of a partial resemblance: a wrong governorate sends a parcel to
 * the wrong end of the country and nobody notices until it comes back. The
 * guess is shown in the ship dialog for a human to confirm or correct, and the
 * submit is refused until an id is actually chosen.
 */
class AbsAddressMatcher
{
    /**
     * The row whose name is the same name, allowing for how Arabic is typed.
     *
     * @param  list<array{id: int, label: string}>  $options
     * @return array{id: int, label: string}|null
     */
    public function match(?string $stored, array $options): ?array
    {
        $needle = $this->normalize($stored);

        if ($needle === '') {
            return null;
        }

        foreach ($options as $option) {
            if ($this->normalize($option['label']) === $needle) {
                return $option;
            }
        }

        /*
         * One containment match, and only one. "أكتوبر" inside "مدينة 6 أكتوبر"
         * is the same place; but if two rows both contain it, the text does not
         * identify a place and guessing between them is worse than admitting
         * there is no match.
         */
        $contained = array_values(array_filter(
            $options,
            function (array $option) use ($needle) {
                $label = $this->normalize($option['label']);

                return $label !== '' && (str_contains($label, $needle) || str_contains($needle, $label));
            },
        ));

        return count($contained) === 1 ? $contained[0] : null;
    }

    /**
     * One spelling of an Arabic place name, so the variants collapse together.
     *
     * Handles what actually differs between a buyer's typing and a courier's
     * database: the alef forms (أ إ آ ا), ta marbuta against ha (ة ه), alef
     * maqsura against ya (ى ي), the optional "ال" article, Arabic-Indic digits
     * against Western ones, diacritics, and any amount of whitespace or
     * punctuation between words.
     */
    public function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');

        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه',
            'ى' => 'ي', 'ئ' => 'ي',
            'ؤ' => 'و',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        /* Tashkeel and the tatweel stretching character carry no meaning for a
           name lookup, and a buyer's keyboard may or may not produce them. */
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0640}\x{06D6}-\x{06ED}]/u', '', $value);

        /* Everything that is not a letter or a digit becomes one space, so
           "6th of October" and "6-th  of october" reduce alike. */
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        /* The article is written as often as it is left off ("الجيزة" vs
           "جيزة"), on both sides of the comparison. */
        $value = preg_replace('/(^|\s)ال(?=\p{Arabic})/u', '$1', $value);

        return trim($value);
    }
}
