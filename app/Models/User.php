<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use App\Traits\MediaImageTrait;
class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use InteractsWithMedia;
    use HasSlug;
    use MediaImageTrait;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'slug',
        'partner_id',
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
