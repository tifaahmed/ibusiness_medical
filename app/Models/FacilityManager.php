<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacilityManager extends Model
{
    use HasFactory;

    /*
     * A manager removed from the facility form (or cascaded with the whole
     * facility) is soft-deleted, not dropped — see the `deleted_at` migration.
     */
    use SoftDeletes;

    protected $fillable = [
        'facility_id',
        'name',
        'position',
        'phones',
        'created_by',
    ];

    protected $casts = [
        'phones' => 'array',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
