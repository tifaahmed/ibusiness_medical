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
        $byResource = [];
        foreach ($value as $perm) {
            if (!is_string($perm)) {
                continue;
            }
            $resource = UserPermissionEnum::resourceFor($perm);
            if ($resource === null) {
                continue;
            }
            $byResource[$resource][] = $perm;
        }
        foreach ($byResource as $resource => $perms) {
            if (count(array_unique($perms)) > 1) {
                $fail("Cannot grant both \"manage {$resource}\" and \"manage own {$resource}\". Pick one.");
                return;
            }
        }
    }
}
