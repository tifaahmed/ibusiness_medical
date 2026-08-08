<?php

namespace Database\Seeders;

use App\Models\Contract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $partnerImages = [
            'WhatsApp Image 2026-01-03 at 11.04.09 PM.jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.10 PM (3).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.10 PM (2).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.10 PM (1).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.10 PM.jpeg',
            'addbf5f5-9add-48dd-86ab-2ddd3324c56e.jpg',
            'f5e7c8e5-db5a-4e3d-b6a9-decba44638e1.jpg',
            'WhatsApp Image 2026-01-03 at 11.04.11 PM (3).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.11 PM (2).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.11 PM (1).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.11 PM.jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.12 PM (2).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.12 PM (1).jpeg',
            'WhatsApp Image 2026-01-03 at 11.04.12 PM.jpeg',
        ];

        foreach ($partnerImages as $index => $imageName) {
            $number = $index + 1;

            $contract = Contract::updateOrCreate([
                'slug' => Str::slug("partner-{$number}"),
            ], [
                'name' => [
                    'ar' => "شريك {$number}",
                    'en' => "Partner {$number}",
                ],
                'description' => [
                    'ar' => "وصف الشريك {$number}",
                    'en' => "Description for Partner {$number}",
                ],
                'is_active' => true,
                'sort_order' => $number,
            ]);

            $imagePath = public_path("images/partners/{$imageName}");

            if (file_exists($imagePath) && ! $contract->hasMedia('image')) {
                $contract->addMedia($imagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('image');
            }
        }
    }
}
