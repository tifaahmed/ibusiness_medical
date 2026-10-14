<?php

namespace App\Models;

use App\Enums\FamilyMember\ApprovalStatusEnum;
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
        'national_id',
        'approval_status',
        'added_by',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    public const ADDED_BY_ADMIN = 'admin';

    public const ADDED_BY_MEMBER = 'member';

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'relationship' => RelationshipEnum::class,
        'approval_status' => ApprovalStatusEnum::class,
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Staff turning a member on IS the approval: a pending row never goes
        // active while still reading "pending".
        static::saving(function (self $familyMember) {
            if ($familyMember->is_active && $familyMember->approval_status !== ApprovalStatusEnum::APPROVED) {
                $familyMember->approval_status = ApprovalStatusEnum::APPROVED;
                $familyMember->approved_at ??= now();
            }
        });
    }

    public function registerMediaCollections(): void
    {
        // ID scans are private: the 'local' disk is not web-served, staff read them through a gated route.
        $this->addMediaCollection('national_id_front')->singleFile()->useDisk('local');
        $this->addMediaCollection('national_id_back')->singleFile()->useDisk('local');
    }

    public function isPending(): bool
    {
        return $this->approval_status === ApprovalStatusEnum::PENDING;
    }

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

