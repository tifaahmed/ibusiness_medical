<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One credit (+) or debit (-) of a member's money wallet, in EGP. Append-only: the balance is the sum. */
class WalletTransaction extends Model
{
    public const SOURCE_POINTS = 'points';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_TRANSFER = 'transfer';

    public const SOURCE_ORDER = 'order';

    public const SOURCE_REFUND = 'refund';

    public const SOURCE_MEMBERSHIP = 'membership';

    public const SOURCE_TOPUP = 'topup';

    protected $fillable = ['user_id', 'source', 'client_id', 'amount', 'points', 'points_per_egp', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'points' => 'integer', 'points_per_egp' => 'decimal:4'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who made it, when it was made by hand (`source = admin`). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A member's wallet balance in EGP, from every row. */
    public static function balanceOf(int $userId): float
    {
        return round((float) static::query()->where('user_id', $userId)->sum('amount'), 2);
    }
}
