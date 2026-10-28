<?php

use App\Models\Setting;
use App\Support\CardPrint;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;

/**
 * The back now has its own width and height instead of a "smaller by" figure. The new rows start at the front minus the
 * old reduction, so a back prints the same size it did until an admin edits it. Only the setting row this feature added
 * is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reduce = (float) (Setting::query()->where('slug', 'card_back_reduce_cm')->value('value') ?? 0.5);
        $front = ['w' => CardPrint::width(), 'h' => CardPrint::height()];

        foreach (CardPrint::seedRows() as $row) {
            if (Setting::query()->where('slug', $row['slug'])->exists()) {
                continue;
            }
            if ($row['slug'] === CardPrint::BACK_WIDTH_CM) {
                $row['value'] = (string) max(1, round($front['w'] - $reduce, 2));
            } elseif ($row['slug'] === CardPrint::BACK_HEIGHT_CM) {
                $row['value'] = (string) max(1, round($front['h'] - $reduce, 2));
            }
            SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
        }

        Setting::query()->where('slug', 'card_back_reduce_cm')->delete();
        SiteSettings::forget();
    }

    public function down(): void
    {
        Setting::query()->whereIn('slug', [CardPrint::BACK_WIDTH_CM, CardPrint::BACK_HEIGHT_CM])->delete();
        SiteSettings::forget();
    }
};
