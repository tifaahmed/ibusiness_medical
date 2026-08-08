<?php

namespace App\Models;

use App\Enums\FamilyMember\RelationshipEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FamilyMember extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'membership_id',
        'name',
        'relationship',
        'date_of_birth',
        'phone',
        'email',
        'photo_path',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'relationship' => RelationshipEnum::class,
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    // Get the main user (father) through membership
    public function getOwnerAttribute()
    {
        return $this->membership->user;
    }
}

