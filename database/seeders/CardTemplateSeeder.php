<?php

namespace Database\Seeders;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Models\CardTemplate;
use App\Support\CardTemplateLayoutDefaults;
use Illuminate\Database\Seeder;

class CardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => ['ar' => 'كارت ديلر للعائلة (شريك)', 'en' => 'Deilar Family Card (Partner)'],
                'slug' => 'deilar-family-card-partner',
                'status' => CardTemplateStatusEnum::WITH_PARTNER->value,
            ],
            [
                'name' => ['ar' => 'كارت ديلر للعائلة', 'en' => 'Deilar Family Card'],
                'slug' => 'deilar-family-card',
                'status' => CardTemplateStatusEnum::NO_PARTNER->value,
            ],
        ];

        foreach ($templates as $template) {
            CardTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'status' => $template['status'],
                    'card_empty' => 'images/cards/deilar-card-blank.png',
                    'sample_card' => 'images/cards/deilar-card-full.png',
                    // Both come from the same source of truth the admin UI is
                    // served from, so a seeded template and a freshly created
                    // one start identical.
                    'layout' => CardTemplateLayoutDefaults::layout(),
                    'sample_data' => CardTemplateLayoutDefaults::sampleData(),
                ],
            );
        }
    }
}
