<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One notification an admin sent to the apps — also the audit row (who, what, to how many, how it ended). */
class PushNotification extends Model
{
    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'title', 'body', 'url', 'audience', 'audience_phone', 'audience_filters', 'status', 'total', 'delivered', 'failed', 'removed', 'error', 'created_by', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime', 'audience_filters' => 'array'];
    }

    /** The phones typed for a "specific members" send: split on commas, spaces or lines, digits only, no repeats. @return array<int, string> */
    public static function parsePhones(?string $raw): array
    {
        $phones = preg_split('/[\s,;،]+/u', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(array_map(fn ($p) => preg_replace('/\D+/', '', $p), $phones))));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
