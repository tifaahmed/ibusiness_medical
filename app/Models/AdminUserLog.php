<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminUserLog extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_BLOCKED = 'blocked';

    public const ACTION_UNBLOCKED = 'unblocked';

    public const ACTION_DELETED = 'deleted';

    protected $fillable = [
        'user_id', 'user_name', 'user_email', 'admin_id', 'admin_name', 'action',
        'old_values', 'new_values', 'changed_fields', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** What the log keeps of an account. Never the password hash. */
    public static function snapshot(User $user): array
    {
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'partner_id' => $user->partner_id,
            'email_verified' => $user->email_verified_at !== null,
            'dashboard_blocked' => $user->dashboard_blocked_at !== null,
            'roles' => $user->getRoleNames()->sort()->values()->all(),
            'permissions' => $user->getDirectPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }

    /**
     * File one entry. A failed log must never cost the admin the save:
     * warn and carry on.
     */
    public static function record(
        User $subject,
        ?User $actor,
        string $action,
        ?array $old,
        ?array $new,
        ?Request $request = null,
        ?array $changed = null,
    ): ?self {
        try {
            if ($changed === null && $old !== null && $new !== null) {
                $changed = array_values(array_filter(
                    array_keys($new),
                    fn ($k) => ! array_key_exists($k, $old) || $old[$k] !== $new[$k],
                ));
            }

            return self::create([
                'user_id' => $subject->id,
                'user_name' => $new['name'] ?? $old['name'] ?? $subject->name,
                'user_email' => $new['email'] ?? $old['email'] ?? $subject->email,
                'admin_id' => $actor?->id,
                'admin_name' => $actor?->name,
                'action' => $action,
                'old_values' => $old,
                'new_values' => $new,
                'changed_fields' => $changed,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write admin user log', [
                'user_id' => $subject->id,
                'action' => $action,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
