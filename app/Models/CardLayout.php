<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardLayout extends Model
{
    protected $table = 'membership_cards';

    protected $fillable = [
        'membership_id',
        'partner_id',
        'partner_x',
        'partner_y',
        'partner_scale',
        'photo_x',
        'photo_y',
        'photo_scale',
        'name_x',
        'name_y',
        'name_scale',
        'name_color',
        'fields_x',
        'fields_y',
        'fields_scale',
        'fields_color',
        'qr_x',
        'qr_y',
        'qr_scale',
        'generated_image_path',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'partner_x' => 'decimal:2',
            'partner_y' => 'decimal:2',
            'partner_scale' => 'decimal:3',
            'photo_x' => 'decimal:2',
            'photo_y' => 'decimal:2',
            'photo_scale' => 'decimal:3',
            'name_x' => 'decimal:2',
            'name_y' => 'decimal:2',
            'name_scale' => 'decimal:3',
            'fields_x' => 'decimal:2',
            'fields_y' => 'decimal:2',
            'fields_scale' => 'decimal:3',
            'qr_x' => 'decimal:2',
            'qr_y' => 'decimal:2',
            'qr_scale' => 'decimal:3',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}