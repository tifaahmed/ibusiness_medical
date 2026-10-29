<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use App\Models\Concerns\GuardsUniqueIdentity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Membership extends Model implements HasMedia
{
    use GuardsUniqueIdentity;
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
        'plan',
        'source',
        'trial_started_at',
        'member_type',
        'parent_membership_id',
        'relationship',
        'approval_status',
        'added_by',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'legacy_family_member_id',
        'legacy_notes',
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
            'trial_started_at' => 'datetime',
            'approved_at' => 'datetime',
            'legacy_notes' => 'array',
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

    public const TYPE_PRIMARY = 'primary';

    public const TYPE_RELATIVE = 'relative';

    public const ADDED_BY_ADMIN = 'admin';

    public const ADDED_BY_MEMBER = 'member';

    public function isRelative(): bool
    {
        return $this->member_type === self::TYPE_RELATIVE;
    }

    public function scopePrimary($query)
    {
        return $query->where($query->getModel()->getTable().'.member_type', self::TYPE_PRIMARY);
    }

    public function scopeRelatives($query)
    {
        return $query->where($query->getModel()->getTable().'.member_type', self::TYPE_RELATIVE);
    }

    /**
     * The relatives held by this primary card (soft-deleted ones excluded).
     */
    public function relatives(): HasMany
    {
        return $this->hasMany(self::class, 'parent_membership_id');
    }

    /**
     * The primary card a relative belongs to.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_membership_id');
    }

    /**
     * The card whose subscription governs this one: a relative has none of its
     * own and is exactly as valid as the primary it hangs on.
     */
    public function governingCard(): self
    {
        return $this->isRelative() ? ($this->parent ?? $this) : $this;
    }

    /**
     * A six-digit number nobody holds (the card prints it as 123-456; the dash
     * is never stored). Used for every new member, relatives included.
     */
    public static function newNumber(): string
    {
        do {
            $number = (string) random_int(100000, 999999);
        } while (static::withTrashed()->where(fn ($q) => $q->where('membership_number', $number)->orWhere('slug', $number))->exists());

        return $number;
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

    public const PLAN_TRIAL = 'trial';

    public const PLAN_FULL = 'full';

    public const PLAN_RELATIVE = 'relative';

    /** Where a self-issued card came from. */
    public const SOURCE_SELF_SERVICE = 'self_service';

    /** Card states, as clients draw them. */
    public const STATE_PENDING = 'pending';

    public const STATE_TRIAL = 'trial';

    public const STATE_TRIAL_EXPIRED = 'trial_expired';

    public const STATE_ACTIVE = 'active';

    public const STATE_EXPIRED = 'expired';

    public const STATE_INACTIVE = 'inactive';

    public function isTrial(): bool
    {
        return $this->plan === self::PLAN_TRIAL;
    }

    public function isExpired(): bool
    {
        return $this->expiration_date?->isPast() ?? false;
    }

    /**
     * The ONE answer to "what is this card right now" — every scan page, API
     * and app reads this, so they cannot disagree. Derived from the dates each
     * time rather than stored, so an expiry needs no job to take effect.
     */
    public function state(): string
    {
        if ($this->isRelative()) {
            return $this->governingCard()->state();
        }

        if (\App\Services\Otp\MemberRegistration::isPending($this->membership_number)) {
            return self::STATE_PENDING;
        }

        if ($this->isTrial()) {
            return $this->isExpired() ? self::STATE_TRIAL_EXPIRED : ($this->is_active ? self::STATE_TRIAL : self::STATE_INACTIVE);
        }

        if (! $this->is_active) {
            return self::STATE_INACTIVE;
        }

        return $this->isExpired() ? self::STATE_EXPIRED : self::STATE_ACTIVE;
    }

    /** Whole days left (0 when expired or undated). */
    public function daysLeft(): int
    {
        return $this->expiration_date === null ? 0 : max(0, (int) ceil(now()->floatDiffInDays($this->expiration_date, false)));
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MembershipSubscription::class);
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
        if ($this->isRelative()) {
            return (bool) $this->is_active && $this->governingCard()->isCurrent();
        }

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
        // A relative's photo and ID scans (the scans are private: the 'local' disk is not web-served).
        $this->addMediaCollection('photo')->singleFile();
        $this->addMediaCollection('national_id_front')->singleFile()->useDisk('local');
        $this->addMediaCollection('national_id_back')->singleFile()->useDisk('local');
    }

    /**
     * Boot the model.
     */
    /**
     * How a card number is PRINTED: six digits get a dash after the first three
     * (123456 -> 123-456). Only styling — the stored number, the barcode, the QR
     * and every lookup keep the plain digits; older 4-digit and imported
     * numbers are shown as they are.
     */
    public static function displayNumber(string $number): string
    {
        return preg_match('/^\d{6}$/', $number) === 1 ? substr($number, 0, 3).'-'.substr($number, 3) : $number;
    }

    /**
     * The rules of a relative, enforced on EVERY write path:
     * it names a relation and a PRIMARY card (never another relative), has no
     * plan or expiry of its own, and carries at least a phone, a date of birth
     * or a National ID. A row copied from the old family table is exempt from
     * the last rule (it may have had none — it must still arrive).
     */
    protected static function guardRelative(self $membership): void
    {
        if (! $membership->isRelative()) {
            if ($membership->parent_membership_id !== null) {
                throw ValidationException::withMessages(['member_type' => ['Only a relative can belong to another member.']]);
            }

            return;
        }

        if (! $membership->relationship) {
            throw ValidationException::withMessages(['relationship' => ['A relative needs a relationship.']]);
        }

        $parent = $membership->parent_membership_id ? static::withTrashed()->find($membership->parent_membership_id) : null;

        if ($parent === null || $parent->isRelative()) {
            throw ValidationException::withMessages(['parent_membership_id' => ['A relative must belong to a primary member (not to another relative).']]);
        }

        // Staff turning a relative on IS the approval: a pending row never goes active while still reading "pending".
        if ($membership->is_active && $membership->approval_status !== 'approved') {
            $membership->approval_status = 'approved';
            $membership->approved_at ??= now();
        }

        // No subscription of its own.
        $membership->plan = self::PLAN_RELATIVE; // the column is NOT NULL; 'relative' is never trial or full
        $membership->expiration_date = null;
        $membership->trial_started_at = null;

        // Rows copied from the old family table may have had none of the three: they must still arrive.
        if ($membership->legacy_family_member_id !== null || ($membership->exists && ! $membership->isDirty('national_id'))) {
            return;
        }

        $user = $membership->user_id ? User::withTrashed()->find($membership->user_id) : null;

        if (blank($membership->national_id) && blank($user?->phone) && blank($user?->date_of_birth)) {
            throw ValidationException::withMessages(['phone' => ['Enter at least a phone, a date of birth or a National ID for the relative.']]);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(fn (self $membership) => static::guardUnique($membership, 'national_id', 'This National ID already belongs to another card.'));

        static::saving(fn (self $membership) => static::guardRelative($membership));

        // A primary card that goes takes its relatives with it (each through delete(), so their own events run).
        static::deleting(function (self $membership) {
            if ($membership->isRelative()) {
                return;
            }
            $membership->relatives()->get()->each(fn (self $relative) => $membership->isForceDeleting() ? $relative->forceDelete() : $relative->delete());
        });

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
