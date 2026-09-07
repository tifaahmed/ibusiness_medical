<?php

namespace App\Services\Otp;

use App\Models\User;

/**
 * How much of a member's registration is filled in, as two percentages.
 *
 * Every field on the storefront's registration form is optional — somebody
 * proves they hold a phone and is let straight in — so nothing here gates
 * anything. The progress is a NUDGE: a bar and a "finish your details" prompt
 * that stop appearing once there is nothing left to ask for.
 *
 * Two stages, because they are asked at different moments and mean different
 * things:
 *
 * - `essentials`, asked immediately after signing up for the first time. The
 *   five things customer services needs in order to ring somebody back and know
 *   who they are ringing.
 * - `details`, a page the member can open whenever they like afterwards. The
 *   rest of what ends up on a card.
 *
 * Worked out HERE rather than on the storefront for the same reason the OTP
 * policy is: this application owns the data, so it owns the question of what
 * counts as filled in. A storefront computing its own percentage would drift
 * the moment a field is added.
 */
final class RegistrationProgress
{
    /**
     * The first ask: enough to call somebody back and know who they are.
     *
     * @var list<string>
     */
    public const ESSENTIAL_FIELDS = ['name', 'email', 'gender', 'governorate_id', 'city_id'];

    /**
     * The rest, filled in at leisure.
     *
     * @var list<string>
     */
    public const DETAIL_FIELDS = ['national_id', 'job_title', 'company_name', 'address', 'avatar'];

    /**
     * Both bars for `$member`, and whether either still has anything to ask.
     *
     * @return array{
     *     essentials: array{filled: int, total: int, percent: int, missing: list<string>, complete: bool},
     *     details: array{filled: int, total: int, percent: int, missing: list<string>, complete: bool},
     *     pending_card: bool,
     * }
     */
    public function for(User $member): array
    {
        $values = $this->values($member);

        $membership = $member->memberships()->latest('id')->first();

        return [
            'essentials' => $this->stage(self::ESSENTIAL_FIELDS, $values),
            'details' => $this->stage(self::DETAIL_FIELDS, $values),
            /*
             * Whether this member is still waiting on a real card. The
             * storefront shows the "customer services will be in touch" line
             * off this rather than off the progress: somebody can fill in every
             * field and still not have been issued a card, and telling them the
             * form is done is not the same as telling them the card is.
             */
            'pending_card' => MemberRegistration::isPending($membership?->membership_number),
        ];
    }

    /**
     * One bar.
     *
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $values
     * @return array{filled: int, total: int, percent: int, missing: list<string>, complete: bool}
     */
    private function stage(array $fields, array $values): array
    {
        $missing = array_values(array_filter(
            $fields,
            fn (string $field) => ! $this->filled($values[$field] ?? null),
        ));

        $total = count($fields);
        $filled = $total - count($missing);

        return [
            'filled' => $filled,
            'total' => $total,
            /*
             * Rounded rather than floored so a member one field short of the
             * end never sees 100%, and one field in never sees 0%. An empty
             * stage would divide by zero, which is why `$total` is guarded even
             * though both lists above are non-empty today.
             */
            'percent' => $total === 0 ? 100 : (int) round(($filled / $total) * 100),
            'missing' => $missing,
            'complete' => $missing === [],
        ];
    }

    /**
     * Everything the two lists above ask about, read off the member.
     *
     * @return array<string, mixed>
     */
    private function values(User $member): array
    {
        $membership = $member->memberships()->latest('id')->first();

        return [
            'name' => $member->name,
            'email' => $member->email,
            'gender' => $member->gender,
            'governorate_id' => $membership?->governorate_id,
            'city_id' => $membership?->city_id,
            'national_id' => $membership?->national_id,
            /*
             * The raw translation, not `getTranslation()`: a member who filled
             * in an Arabic job title has answered the question, and reading it
             * through the current locale would report the field empty to an
             * English visitor.
             */
            'job_title' => $membership?->getTranslations('job_title'),
            'company_name' => $membership?->company_id,
            'address' => $membership?->addresses()->exists(),
            'avatar' => $member->getMedia('avatar')->isNotEmpty(),
        ];
    }

    /**
     * Whether a value counts as answered.
     *
     * A translatable field arrives as a map of locale to string, and one whose
     * every translation is blank is empty however many keys it has — that is
     * what a cleared box leaves behind.
     */
    private function filled(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_filter($value, fn ($item) => trim((string) $item) !== '') !== [];
        }

        return $value !== null && trim((string) $value) !== '';
    }
}
