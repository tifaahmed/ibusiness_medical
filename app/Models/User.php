<?php

namespace App\Models;

use App\Enums\User\UserPermissionEnum;
use App\Enums\User\UserRoleEnum;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\MediaImageTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\GuardsUniqueIdentity;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use GuardsUniqueIdentity;
    use HasRoles;
    use HasSlug;
    use InteractsWithMedia;
    use MediaImageTrait;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The phone with spaces, dashes, plus signs and brackets removed, as SQL.
     * The indexed `phone_digits` column when the migration has run (an index
     * hit), the same expression computed per row when it has not.
     */
    public static function phoneDigitsSql(): string
    {
        static $column = null;

        $column ??= \Illuminate\Support\Facades\Schema::hasColumn('users', 'phone_digits');

        return $column
            ? 'phone_digits'
            : "REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', '')";
    }

    protected static function booted(): void
    {
        static::saving(fn (self $user) => static::guardUnique($user, 'phone', 'This phone number already belongs to another member.'));
    }

    /**
     * Every permission this user can actually exercise, by name.
     *
     * NOT the same question as `getAllPermissions()`, which reads the rows in
     * `role_has_permissions`. A super admin is granted everything by the
     * `Gate::before` in AppServiceProvider, whatever those rows happen to say,
     * so reading the table for one would report less than the account can
     * really do — and the admin UI hides buttons on exactly this list. The
     * result was a route the super admin could reach by typing the URL, behind
     * a link the page would not draw.
     *
     * The enum is the source of truth here rather than the permissions table:
     * the whole point is to stay right for a permission that has been added in
     * code but not yet seeded.
     *
     * @return list<string>
     */
    public function effectivePermissionNames(): array
    {
        if ($this->hasRole(UserRoleEnum::SUPER_ADMIN)) {
            return UserPermissionEnum::all();
        }

        return $this->getAllPermissions()->pluck('name')->values()->all();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        /* Asked for on the storefront's registration form, always optional. */
        'gender',
        'date_of_birth',
        'password',
        'slug',
        'partner_id',
        /* A login code just for this member; null follows the site setting. */
        'otp_fixed_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'avatar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'dashboard_blocked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        // The slug is the public URL key for a member (/admin/user/membership/{slug}).
        // It is minted once on create and frozen after that, so renaming a member
        // never breaks existing links, bookmarks or QR codes pointing at them.
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Get the memberships for the user.
     */
    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Partner attached to an admin user. Drives the `manage partner memberships`
     * scope: when set, that admin can only see/manage memberships whose
     * partner_id matches this one.
     */
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the active membership for the user.
     */
    public function membership()
    {
        return $this->hasOne(Membership::class)->where('is_active', true)->latest();
    }

    /**
     * Scope a query to only include users with memberships.
     */
    public function scopeWithMemberships($query)
    {
        return $query->whereHas('memberships');
    }

    /**
     * Scope a query to filter users by search term (name or email).
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to only include users with active memberships.
     */
    public function scopeWithActiveMemberships($query)
    {
        return $query->whereHas('memberships', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Scope a query to only include users with only inactive memberships (no active ones).
     */
    public function scopeWithInactiveMemberships($query)
    {
        return $query->whereHas('memberships', function ($q) {
            $q->where('is_active', false);
        })->whereDoesntHave('memberships', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Scope a query to only include trashed users with memberships (including trashed memberships).
     */
    public function scopeWithTrashedMemberships($query)
    {
        return $query->whereHas('memberships', function ($q) {
            $q->withTrashed();
        });
    }

    /**
     * Scope a query to only include trashed users with active memberships (including trashed).
     */
    public function scopeWithTrashedActiveMemberships($query)
    {
        return $query->whereHas('memberships', function ($q) {
            $q->withTrashed()->where('is_active', true);
        });
    }

    /**
     * Scope a query to only include trashed users with inactive memberships (including trashed).
     */
    public function scopeWithTrashedInactiveMemberships($query)
    {
        return $query->whereHas('memberships', function ($q) {
            $q->withTrashed()->where('is_active', false);
        });
    }
}
