<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class OrderLog extends Model
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_PAYMENT_STATUS_CHANGED = 'payment_status_changed';

    public const ACTION_DELIVERY_STATUS_CHANGED = 'delivery_status_changed';

    public const ACTION_CANCELED = 'canceled';

    protected $fillable = [
        'order_id',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(
        ?int $orderId,
        ?int $adminId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?Request $request = null,
    ): self {
        return self::create([
            'order_id' => $orderId,
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
            if (! array_key_exists($key, $old) || $old[$key] !== $value) {
                $changed[] = $key;
            }
        }

        return $changed;
    }
}
