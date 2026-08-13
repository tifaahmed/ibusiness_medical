<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MembershipCard extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'membership_card_patches';

    protected $fillable = [
        'batch_name',
        'prefix',
        'display_prefix',
        'display_groups',
        'layout_overrides',
        'quantity',
        'start_number',
        'membership_ids',
        'created_by',
        'partner_id',
        'card_template_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'start_number' => 'integer',
            'membership_ids' => 'array',
            'layout_overrides' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
        // Optional per-batch logo override — replaces partner.image on the
        // generated cards so the admin can swap branding in one place.
        $this->addMediaCollection('partner_logo')->singleFile();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Partner the whole batch was generated for. All memberships in this
     * batch share this partner — kept denormalized here so we can scope card
     * queries by partner_id without joining through memberships.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * The card design this batch was cut from. `layout_overrides` are per-batch
     * tweaks applied on top of the template's own layout.
     */
    public function cardTemplate(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class);
    }

    /**
     * Fetch the memberships referenced by membership_ids. Returns an empty
     * collection (not null) so callers can iterate without checks.
     */
    public function memberships()
    {
        $ids = $this->membership_ids ?: [];
        if (empty($ids)) {
            return Membership::whereRaw('1 = 0');
        }

        return Membership::whereIn('id', $ids);
    }
}
