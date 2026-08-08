<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class MemberLog extends Model
{
    use HasFactory;

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_RESTORED = 'restored';
    public const ACTION_FORCE_DELETED = 'force_deleted';
    public const ACTION_FAMILY_CREATED = 'family_created';
    public const ACTION_FAMILY_UPDATED = 'family_updated';
    public const ACTION_FAMILY_DELETED = 'family_deleted';

    protected $fillable = [
        'membership_id',
        'admin_id',
        'action',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(
        ?int $membershipId,
        ?int $adminId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?Request $request = null,
    ): self {
        return self::create([
            'membership_id' => $membershipId,
            'admin_id' => $adminId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => self::diffKeys($oldValues, $newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private static function diffKeys(?array $old, ?array $new): ?array
    {
        if ($old === null || $new === null) {
            return null;
        }

        $changed = [];
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old) || $old[$key] !== $value) {
                $changed[] = $key;
            }
        }
        return $changed;
    }
}
