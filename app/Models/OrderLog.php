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

    /** The order's own outcome moved — pending / success / failed. */
    public const ACTION_ORDER_STATUS_CHANGED = 'order_status_changed';

    public const ACTION_CANCELED = 'canceled';

    /** An admin opened the order's page. A read, not a change. */
    public const ACTION_VIEWED = 'viewed';

    /** An admin opened the edit form — intent to change, before any change. */
    public const ACTION_EDIT_VIEWED = 'edit_viewed';

    /** The lines of the order were added to, re-priced or removed. */
    public const ACTION_PRODUCTS_CHANGED = 'products_changed';

    /**
     * How long the same admin's repeat visit to the same order is folded into
     * the visit already logged. Without it, one admin refreshing an order page
     * buries every real change under a column of identical rows.
     */
    public const VISIT_THROTTLE_MINUTES = 10;

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

    /**
     * Record a read of the order — opening its page or its edit form.
     *
     * Returns null when the same admin already logged that visit inside the
     * throttle window, so a refreshed page does not become an audit trail of
     * its own. Nothing changed, so old/new values stay empty; what is worth
     * keeping is who looked, at what, and from where.
     */
    public static function recordVisit(
        ?int $orderId,
        ?int $adminId,
        string $action,
        ?Request $request = null,
    ): ?self {
        $recent = self::query()
            ->where('order_id', $orderId)
            ->where('admin_id', $adminId)
            ->where('action', $action)
            ->where('created_at', '>=', now()->subMinutes(self::VISIT_THROTTLE_MINUTES))
            ->exists();

        if ($recent) {
            return null;
        }

        return self::record($orderId, $adminId, $action, null, null, $request);
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
