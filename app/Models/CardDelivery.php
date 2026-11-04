<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** One hand-over of one or more cards to a member. A member can have many; photos of the cards are the `cards` media. */
class CardDelivery extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const PHOTOS = 'cards';

    public const STATUSES = ['scheduled', 'delivered', 'failed'];

    protected $fillable = ['user_id', 'courier_id', 'status', 'cards_count', 'card_numbers', 'delivered_at', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['delivered_at' => 'date', 'cards_count' => 'integer'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(DeliveryCourier::class, 'courier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function noteLines(): HasMany
    {
        return $this->hasMany(CardDeliveryNote::class)->orderBy('id');
    }
}
