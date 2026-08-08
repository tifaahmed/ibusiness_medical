<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Core seeders (users, roles, etc.)
            // AdminRoleSeeder::class,
            PermissionSeeder::class,
            // AdminUserSeeder::class,
            // EditorAdminSeeder::class,
            // CitySeeder::class,
            // MemberUserSeeder::class,
            
            // AlexandriaData\AlexandriaDataSeeder::class,
            // AssiutData\AssiutDataSeeder::class,
            // AswanData\AswanDataSeeder::class,
            // BeheiraData\BeheiraDataSeeder::class,
            // BeniSuefData\BeniSuefDataSeeder::class,
            // CairoData\CairoDataSeeder::class,
            // DakahliaData\DakahliaDataSeeder::class,
            // DamiettaData\DamiettaDataSeeder::class,
            // FayoumData\FayoumDataSeeder::class,
            // GizaData\GizaDataSeeder::class,
            // IsmailiaData\IsmailiaDataSeeder::class,
            // KafrElSheikhData\KafrElSheikhDataSeeder::class,
            // LuxorData\LuxorDataSeeder::class,
            // MenofiaData\MenofiaDataSeeder::class,
            // MinyaData\MinyaDataSeeder::class,
            // NewValleyData\NewValleyDataSeeder::class,
            // NorthSinaiData\NorthSinaiDataSeeder::class,
            // PortSaidData\PortSaidDataSeeder::class,
            // QalyubiaData\QalyubiaDataSeeder::class,
            // QenaData\QenaDataSeeder::class,
            // RedSeaData\RedSeaDataSeeder::class,
            // SharqiaData\SharqiaDataSeeder::class,
            // SohagData\SohagDataSeeder::class,
            // SouthSinaiData\SouthSinaiDataSeeder::class,
            // SuezData\SuezDataSeeder::class,
            // SuezGharbiaData\SuezGharbiaDataSeeder::class,

            // ContractSeeder::class,
            // FaqSeeder::class,
            ServiceTypeSeeder::class,
            ServiceSeeder::class,
            PartnerOfferSeeder::class,
            TagSeeder::class
        ]);
    }
}
