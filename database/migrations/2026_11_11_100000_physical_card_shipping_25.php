<?php

use App\Models\Setting;
use App\Support\PhysicalCard;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * Owner decision 2026-11-11: shipping a physical-card order costs 25 EGP (was 50); the card itself stays 50.
 * Only a row still holding the old seeded 50 is moved; a value an admin set on purpose is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = Setting::query()->where('slug', PhysicalCard::SHIPPING)->first();

        if ($row === null) {
            foreach (PhysicalCard::seedRows() as $seed) {
                if ($seed['slug'] === PhysicalCard::SHIPPING) {
                    SiteSettings::put($seed['slug'], $seed['value'], $seed['value_type'], $seed['name']);
                }
            }
        } elseif ((float) $row->value === 50.0) {
            SiteSettings::put(PhysicalCard::SHIPPING, '25', Setting::TYPE_NUMBER);
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        // Nothing to undo: the previous value was a default, not data.
    }
};
