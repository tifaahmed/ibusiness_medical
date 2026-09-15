<?php

namespace App\Rules;

use App\Enums\User\UserPermissionEnum;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reject permission sets that include both `manage X` and `manage own X`
 * for the same resource. The two are mutually exclusive: full access wins,
 * so granting both is always a configuration mistake.
 */
class PermissionsNotMutuallyExclusive implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach (UserPermissionEnum::pairs() as [$full, $own]) {
            if (in_array($full, $value, true) && in_array($own, $value, true)) {
                $fail("Cannot grant both \"{$full}\" and \"{$own}\". Pick one.");
                return;
            }
        }
    }
}
