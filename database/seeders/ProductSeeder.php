<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductType;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo catalogue: 50 products spread over a handful of medical product types.
 *
 * Kept out of DatabaseSeeder on purpose — running it is an explicit act
 * (`php artisan db:seed --class=ProductSeeder`), so a routine reseed does not
 * quietly pile up another fifty fake rows.
 */
class ProductSeeder extends Seeder
{
    private const COUNT = 50;

    /** @var array<int, array{ar: string, en: string}> */
    private const TYPES = [
        ['ar' => 'أجهزة تشخيص', 'en' => 'Diagnostic Devices'],
        ['ar' => 'مستلزمات جراحية', 'en' => 'Surgical Supplies'],
        ['ar' => 'أثاث طبي', 'en' => 'Medical Furniture'],
        ['ar' => 'العناية بالجروح', 'en' => 'Wound Care'],
        ['ar' => 'أجهزة معملية', 'en' => 'Laboratory Equipment'],
        ['ar' => 'مستلزمات الحماية', 'en' => 'Protective Equipment'],
    ];

    /** @var array<int, array{ar: string, en: string}> */
    private const ITEMS = [
        ['ar' => 'جهاز قياس ضغط الدم الرقمي', 'en' => 'Digital Blood Pressure Monitor'],
        ['ar' => 'سماعة طبية احترافية', 'en' => 'Professional Stethoscope'],
        ['ar' => 'مقياس حرارة بالأشعة تحت الحمراء', 'en' => 'Infrared Thermometer'],
        ['ar' => 'جهاز قياس السكر في الدم', 'en' => 'Blood Glucose Meter'],
        ['ar' => 'مقياس تشبع الأكسجين', 'en' => 'Pulse Oximeter'],
        ['ar' => 'جهاز تخطيط القلب', 'en' => 'ECG Machine'],
        ['ar' => 'جهاز الموجات فوق الصوتية', 'en' => 'Ultrasound Scanner'],
        ['ar' => 'منظار الأذن', 'en' => 'Otoscope'],
        ['ar' => 'منظار العين', 'en' => 'Ophthalmoscope'],
        ['ar' => 'جهاز رذاذ الاستنشاق', 'en' => 'Nebulizer'],
        ['ar' => 'مشرط جراحي معقم', 'en' => 'Sterile Surgical Scalpel'],
        ['ar' => 'ملقط جراحي', 'en' => 'Surgical Forceps'],
        ['ar' => 'مقص جراحي', 'en' => 'Surgical Scissors'],
        ['ar' => 'خيوط جراحية', 'en' => 'Surgical Sutures'],
        ['ar' => 'طقم أدوات جراحية', 'en' => 'Surgical Instrument Kit'],
        ['ar' => 'قفازات لاتكس معقمة', 'en' => 'Sterile Latex Gloves'],
        ['ar' => 'كمامات طبية', 'en' => 'Medical Face Masks'],
        ['ar' => 'واقي وجه شفاف', 'en' => 'Protective Face Shield'],
        ['ar' => 'مريول جراحي', 'en' => 'Surgical Gown'],
        ['ar' => 'غطاء أحذية طبي', 'en' => 'Medical Shoe Covers'],
        ['ar' => 'سرير طبي كهربائي', 'en' => 'Electric Hospital Bed'],
        ['ar' => 'كرسي متحرك قابل للطي', 'en' => 'Folding Wheelchair'],
        ['ar' => 'طاولة فحص طبي', 'en' => 'Examination Table'],
        ['ar' => 'عكاز طبي قابل للتعديل', 'en' => 'Adjustable Walking Cane'],
        ['ar' => 'مشاية طبية', 'en' => 'Medical Walker'],
        ['ar' => 'حامل محاليل وريدية', 'en' => 'IV Drip Stand'],
        ['ar' => 'عربة طوارئ', 'en' => 'Emergency Crash Cart'],
        ['ar' => 'خزانة أدوية', 'en' => 'Medicine Cabinet'],
        ['ar' => 'ضمادات معقمة', 'en' => 'Sterile Bandages'],
        ['ar' => 'شاش طبي', 'en' => 'Medical Gauze'],
        ['ar' => 'لاصق طبي', 'en' => 'Medical Adhesive Tape'],
        ['ar' => 'محلول مطهر', 'en' => 'Antiseptic Solution'],
        ['ar' => 'كحول طبي', 'en' => 'Medical Alcohol'],
        ['ar' => 'قطن طبي معقم', 'en' => 'Sterile Cotton Wool'],
        ['ar' => 'حقيبة إسعافات أولية', 'en' => 'First Aid Kit'],
        ['ar' => 'مجهر مخبري', 'en' => 'Laboratory Microscope'],
        ['ar' => 'جهاز طرد مركزي', 'en' => 'Centrifuge Machine'],
        ['ar' => 'حاضنة معملية', 'en' => 'Laboratory Incubator'],
        ['ar' => 'أنابيب اختبار زجاجية', 'en' => 'Glass Test Tubes'],
        ['ar' => 'ماصة معملية', 'en' => 'Laboratory Pipette'],
        ['ar' => 'ثلاجة حفظ العينات', 'en' => 'Specimen Refrigerator'],
        ['ar' => 'جهاز تعقيم بالبخار', 'en' => 'Steam Autoclave'],
        ['ar' => 'حقن يمكن التخلص منها', 'en' => 'Disposable Syringes'],
        ['ar' => 'قسطرة طبية', 'en' => 'Medical Catheter'],
        ['ar' => 'قناع أكسجين', 'en' => 'Oxygen Mask'],
        ['ar' => 'أسطوانة أكسجين محمولة', 'en' => 'Portable Oxygen Cylinder'],
        ['ar' => 'جهاز شفط طبي', 'en' => 'Medical Suction Unit'],
        ['ar' => 'وسادة تدفئة طبية', 'en' => 'Medical Heating Pad'],
        ['ar' => 'كمادات باردة فورية', 'en' => 'Instant Cold Packs'],
        ['ar' => 'ميزان طبي رقمي', 'en' => 'Digital Medical Scale'],
    ];

    public function run(): void
    {
        $createdBy = User::orderBy('id')->value('id');

        $typeIds = collect(self::TYPES)->map(fn (array $type) => ProductType::firstOrCreate(
            ['slug' => str($type['en'])->slug()->value()],
            ['name' => $type, 'created_by' => $createdBy],
        )->id)->all();

        $tagIds = Tag::pluck('id')->all();

        foreach (range(0, self::COUNT - 1) as $index) {
            $item = self::ITEMS[$index % count(self::ITEMS)];

            // Prices land on realistic .00/.50 marks, and only some products are
            // marked down so the list shows both states.
            $cost = round(mt_rand(8000, 450000) / 100, 2);
            $new = round($cost * (1 + mt_rand(15, 60) / 100), 2);
            $discounted = $index % 3 !== 0;
            $old = $discounted ? round($new * (1 + mt_rand(10, 45) / 100), 2) : null;

            $product = Product::create([
                'name' => $item,
                'short_subject' => [
                    'ar' => 'منتج طبي معتمد بجودة عالية',
                    'en' => 'Certified medical grade product',
                ],
                'description' => [
                    'ar' => 'وصف تجريبي لـ '.$item['ar'].'. هذا المنتج مضاف لأغراض العرض والاختبار فقط.',
                    'en' => 'Demo description for the '.$item['en'].'. Seeded for display and testing only.',
                ],
                'old_price' => $old,
                'new_price' => $new,
                'cost_price' => $cost,
                'profit_price' => round($new - $cost, 2),
                'product_type_id' => $typeIds[$index % count($typeIds)],
                'admin_note' => 'Seeded demo product #'.($index + 1),
                // Every fifth product carries a banner, so the ribbon and its
                // countdown have something to show in the list.
                'banner_config' => $index % 5 === 0 ? Product::normalizeBannerConfig([
                    'enabled' => true,
                    'message_ar' => 'عرض خاص',
                    'message_en' => 'SPECIAL OFFER',
                    'text_color' => '#ffffff',
                    'bg_color' => '#dc2626ff',
                    'font_size' => 15,
                    'angle' => 45,
                    'shadow_color' => '#00000033',
                    'days' => 30,
                ]) : null,
                'created_by' => $createdBy,
            ]);

            if ($tagIds !== []) {
                shuffle($tagIds);
                $product->tags()->sync(array_slice($tagIds, 0, mt_rand(1, 3)));
            }
        }

        $this->command?->info(self::COUNT.' demo products created.');
    }
}
