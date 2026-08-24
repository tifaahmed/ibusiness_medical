<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Membership extends Model implements HasMedia
{
    use HasFactory;
    use HasSlug;
    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    public $translatable = ['job_title'];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        // Frozen after create — see User::getSlugOptions(). Editing the membership
        // number must not move the membership's public URL.
        return SlugOptions::create()
            ->generateSlugsFrom('membership_number')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'partner_id',
        'sales_id',
        'governorate_id',
        'city_id',
        'created_by',
        'membership_number',
        'national_id',
        'slug',
        'registration_date',
        'expiration_date',
        'is_active',
        'is_visible',
        'completed_at',
        'is_paid',
        'payment_type',
        'job_title',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_date' => 'datetime',
            'expiration_date' => 'datetime',
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'completed_at' => 'datetime',
            'is_paid' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the membership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company associated with the membership.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the partner associated with the membership.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the sales associated with the membership.
     */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class);
    }

    /**
     * Get the governorate associated with the membership.
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * Get the city associated with the membership.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the admin (user) who created the membership.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include memberships created by a given admin.
     */
    public function scopeCreatedBy($query, ?int $adminId)
    {
        return $query->where('created_by', $adminId);
    }

    /**
     * Get the family members for the membership.
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * Get the addresses for the membership.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the usages for the membership.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MembershipUsage::class);
    }

    /**
     * Get the audit logs for the membership.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MemberLog::class);
    }

    public function activeHistories(): HasMany
    {
        return $this->hasMany(MemberActiveHistory::class);
    }

    public function latestActiveHistory(): HasOne
    {
        return $this->hasOne(MemberActiveHistory::class)->latest()->with('changer');
    }

    public function cardLayouts(): HasMany
    {
        return $this->hasMany(CardLayout::class);
    }

    public function memberPayments(): HasMany
    {
        return $this->hasMany(MemberPayment::class);
    }

    /**
     * Scope a query to only include active memberships.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to the card with this number, matching the slug too.
     *
     * The slug is what a member sees in their own card URL, so it is what they
     * are most likely to paste in. Kept here rather than repeated at each call
     * site so the partner lookup and the order-time price check can never
     * disagree about what "this number" resolves to.
     */
    public function scopeMatchingNumber($query, string $numberOrSlug)
    {
        return $query->where(fn ($nested) => $nested
            ->where('membership_number', $numberOrSlug)
            ->orWhere('slug', $numberOrSlug));
    }

    /**
     * Whether this card is good today: switched on, and not past its expiry.
     *
     * Expiry is read the same way `PartnerMembershipResource` reports
     * `is_expired`, so a checkout that was told a card is valid and the order
     * that prices it agree.
     */
    public function isCurrent(): bool
    {
        return (bool) $this->is_active && ! ($this->expiration_date?->isPast() ?? false);
    }

    /**
     * Whether a membership number typed at a partner checkout earns the member
     * price.
     *
     * Deliberately a lookup rather than a presence check: `new_price` is the
     * member price, and without this any string in the box would buy the
     * discount. Missing, unknown, hidden, switched off or expired all pay the
     * full price.
     */
    public static function earnsMemberPrice(?string $numberOrSlug): bool
    {
        return static::cardFor($numberOrSlug)?->isCurrent() ?? false;
    }

    /**
     * The card a membership number resolves to, or null if it resolves to none.
     *
     * The one lookup behind both the price decision and everything an admin is
     * shown about it: `earnsMemberPrice()` is this plus `isCurrent()`. Kept
     * that way on purpose — an order page that reports "member card valid"
     * must be reading the same row the checkout priced against, or the two
     * quietly disagree about an order nobody can then explain.
     *
     * `visible()` matches the partner lookup: a hidden card answers "no such
     * card" at the checkout, so it must not quietly price as one.
     *
     * @param  array<int, string>  $with  relations to eager load on the card
     */
    public static function cardFor(?string $numberOrSlug, array $with = []): ?self
    {
        $number = trim((string) $numberOrSlug);

        if ($number === '') {
            return null;
        }

        return static::query()
            ->visible()
            ->matchingNumber($number)
            ->with($with)
            ->first();
    }

    /**
     * Scope a query to only include inactive memberships.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include visible memberships.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope a query to only include hidden memberships.
     */
    public function scopeHidden($query)
    {
        return $query->where('is_visible', false);
    }

    /**
     * Scope a query to only include paid memberships.
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    /**
     * Scope a query to only include unpaid memberships.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    /**
     * Scope a query to order memberships by creation date descending.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Register media collections for the membership.
     *
     * `contract` — single image of the signed contract.
     * `gallery`  — multiple supporting images (e.g. receipts, attachments).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('contract')->singleFile();
        $this->addMediaCollection('gallery');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When creating or updating a membership, ensure only one is active per user
        static::saving(function ($membership) {
            if ($membership->is_active) {
                // Deactivate all other memberships for this user
                static::where('user_id', $membership->user_id)
                    ->where('id', '!=', $membership->id)
                    ->update(['is_active' => false]);
            }
        });

        static::saved(function ($membership) {
            if ($membership->wasChanged('is_active')) {
                $membership->activeHistories()->create([
                    'old_is_active' => $membership->getOriginal('is_active'),
                    'new_is_active' => $membership->is_active,
                    'changed_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
        });
    }
}
