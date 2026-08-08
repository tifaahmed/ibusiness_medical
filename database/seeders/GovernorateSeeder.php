<?php

namespace Database\Seeders;

use App\Models\Governorate;
use App\Models\User;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    /**
     * Governorates keyed by slug: [arabic_name, english_name].
     *
     * The slugs here are what CitySeeder looks its city lists up by, so they
     * must stay in sync with the keys in CitySeeder::$citiesByGovernorate.
     */
    private array $governorates = [
        'alexandria' => ['الإسكندرية', 'Alexandria'],
        'assiut' => ['أسيوط', 'Assiut'],
        'aswan' => ['أسوان', 'Aswan'],
        'beheira' => ['البحيرة', 'Beheira'],
        'beni-suef' => ['بني سويف', 'Beni Suef'],
        'cairo' => ['القاهرة', 'Cairo'],
        'dakahlia' => ['الدقهلية', 'Dakahlia'],
        'damietta' => ['دمياط', 'Damietta'],
        'fayoum' => ['الفيوم', 'Fayoum'],
        'giza' => ['الجيزة', 'Giza'],
        'ismailia' => ['الإسماعيلية', 'Ismailia'],
        'kafr-el-sheikh' => ['كفر الشيخ', 'Kafr El Sheikh'],
        'luxor' => ['الأقصر', 'Luxor'],
        'marsa-matrouh' => ['مرسى مطروح', 'Marsa Matrouh'],
        'menofia' => ['المنوفية', 'Menofia'],
        'minya' => ['المنيا', 'Minya'],
        'new-valley' => ['الوادى الجديد', 'New Valley'],
        'north-sinai' => ['شمال سيناء', 'North Sinai'],
        'port-said' => ['بورسعيد', 'Port Said'],
        'qalyubia' => ['القليوبية', 'Qalyubia'],
        'qena' => ['قنا', 'Qena'],
        'red-sea' => ['البحر الأحمر', 'Red Sea'],
        'sharqia' => ['الشرقية', 'Sharqia'],
        'sohag' => ['سوهاج', 'Sohag'],
        'south-sinai' => ['جنوب سيناء', 'South Sinai'],
        'suez' => ['السويس', 'Suez'],
        'suez-gharbia' => ['السويس / الغربية (طنطا والمحلة)', 'Suez / Gharbia (Tanta & Mahalla)'],
    ];

    public function run(): void
    {
        $createdBy = User::value('id');

        foreach ($this->governorates as $slug => [$nameAr, $nameEn]) {
            Governorate::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['ar' => $nameAr, 'en' => $nameEn],
                    'created_by' => $createdBy,
                ]
            );
        }

        $this->command->info(count($this->governorates) . ' governorates seeded.');
    }
}
