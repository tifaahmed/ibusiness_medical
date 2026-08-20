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
        'card_template_id',
        'layout',
        'field_values',
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
        'barcode_x',
        'barcode_y',
        'barcode_scale',
        'generated_image_path',
        'generated_back_image_path',
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
            'barcode_x' => 'decimal:2',
            'barcode_y' => 'decimal:2',
            'barcode_scale' => 'decimal:3',
            'layout' => 'array',
            'field_values' => 'array',
        ];
    }

    /**
     * Memberships whose card was changed away from its design and so no longer
     * follows it — a saved layout of its own, or, for cards saved before the
     * generator stored whole layouts, any of the old per-element positions.
     *
     * @return array<int, int>
     */
    public static function customisedMembershipIds(): array
    {
        return static::query()
            ->where(function ($q) {
                $q->whereNotNull('layout')
                    ->orWhereNotNull('field_values')
                    ->orWhereNotNull('partner_x')
                    ->orWhereNotNull('qr_x')
                    ->orWhereNotNull('barcode_x')
                    ->orWhereNotNull('fields_x')
                    ->orWhereNotNull('name_x')
                    ->orWhereNotNull('photo_x');
            })
            ->distinct()
            ->pluck('membership_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function cardTemplate(): BelongsTo
    {
        return $this->belongsTo(CardTemplate::class);
    }
}