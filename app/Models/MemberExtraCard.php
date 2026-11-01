<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One extra card (table member_extra_cards) (physical or soft) held by a member. See the migration for the number rule. */
class MemberExtraCard extends Model
{
    use SoftDeletes;

    protected $table = 'member_extra_cards';

    public const KIND_PHYSICAL = 'physical';

    public const KIND_SOFT = 'soft';

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_ORDER = 'order';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['membership_id', 'kind', 'card_number', 'source', 'status', 'parent_card_id', 'order_id', 'created_by', 'note'];

    /** @return list<string> */
    public static function statuses(): array
    {
        return [self::STATUS_REQUESTED, self::STATUS_ACTIVE, self::STATUS_CANCELLED];
    }

    protected static function booted(): void
    {
        // An approved physical card always has a soft copy on the member's phone (same number, follows renumbering);
        // a cancelled physical card takes its copy with it.
        static::saved(function (self $card) {
            if ($card->kind !== self::KIND_PHYSICAL) {
                return;
            }

            $copy = static::query()->where('parent_card_id', $card->id)->first();

            if ($card->status === self::STATUS_ACTIVE && $copy === null) {
                static::query()->create([
                    'membership_id' => $card->membership_id,
                    'kind' => self::KIND_SOFT,
                    'card_number' => $card->card_number,
                    'source' => $card->source,
                    'status' => self::STATUS_ACTIVE,
                    'parent_card_id' => $card->id,
                    'order_id' => $card->order_id,
                    'created_by' => $card->created_by,
                ]);
            } elseif ($copy !== null) {
                $copy->update(['card_number' => $card->card_number, 'status' => in_array($card->status, [self::STATUS_ACTIVE, self::STATUS_CANCELLED], true) ? $card->status : $copy->status]);
            }
        });

        static::deleted(function (self $card) {
            if ($card->kind === self::KIND_PHYSICAL) {
                static::query()->where('parent_card_id', $card->id)->get()->each->delete();
            }
        });
    }

    /** @return list<string> */
    public static function kinds(): array
    {
        return [self::KIND_PHYSICAL, self::KIND_SOFT];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** The number printed / shown on this card: its own, else the member's. */
    public function number(): string
    {
        return $this->card_number ?: (string) $this->membership?->membership_number;
    }

    /**
     * Why `$number` cannot be given to a card of `$membershipId`, or null. The member's own number and numbers
     * already on the SAME member's cards are fine (same number, several cards); anything held by someone else is not.
     */
    public static function numberTakenBy(string $number, int $membershipId, ?int $exceptCardId = null): ?string
    {
        $owner = Membership::withTrashed()
            ->where(fn ($q) => $q->where('membership_number', $number)->orWhere('slug', $number))
            ->where('id', '!=', $membershipId)
            ->exists();

        if ($owner) {
            return 'membership';
        }

        $card = static::query()
            ->where('card_number', $number)
            ->where('membership_id', '!=', $membershipId)
            ->when($exceptCardId, fn ($q) => $q->where('id', '!=', $exceptCardId))
            ->exists();

        return $card ? 'card' : null;
    }
}
