<?php

use App\Models\Setting;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Points sharing and wallet sharing each get their OWN independent switch. Until now `loyalty_transfers_enabled`
 * gated both and the two kind switches only narrowed it. To change nothing for members today, each kind switch is set
 * to what was effectively true (main AND own); the old main switch is kept (never deleted) and re-titled as unused.
 */
return new class extends Migration
{
    public function up(): void
    {
        $main = (bool) SiteSettings::get('loyalty_transfers_enabled', false);

        $kinds = [
            'loyalty_transfers_points_enabled' => ['en' => 'Let members share loyalty POINTS with each other (own switch)', 'ar' => 'السماح للأعضاء بمشاركة نقاط الولاء مع بعضهم (مفتاح مستقل)'],
            'loyalty_transfers_wallet_enabled' => ['en' => 'Let members send WALLET money to each other (own switch)', 'ar' => 'السماح للأعضاء بتحويل رصيد المحفظة لبعضهم (مفتاح مستقل)'],
        ];

        foreach ($kinds as $slug => $name) {
            $own = (bool) SiteSettings::get($slug, true);
            $row = DB::table('settings')->where('slug', $slug)->first();
            if ($row === null) {
                SiteSettings::put($slug, $main && $own, Setting::TYPE_BOOLEAN, $name);
            } else {
                SiteSettings::put($slug, $main && $own, Setting::TYPE_BOOLEAN);
                DB::table('settings')->where('slug', $slug)->update(['name' => json_encode($name, JSON_UNESCAPED_UNICODE)]);
            }
        }

        DB::table('settings')->where('slug', 'loyalty_transfers_enabled')->update(['name' => json_encode([
            'en' => 'OLD main transfer switch — no longer used. Use "share loyalty points" and "send wallet money" instead',
            'ar' => 'مفتاح التحويل الرئيسي القديم — لم يعد مستخدماً. استخدم مفتاح "مشاركة نقاط الولاء" ومفتاح "تحويل المحفظة" كل على حدة',
        ], JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void {}
};
