<?php

namespace Database\Seeders;

use App\Models\PartnerOffer;
use App\Enums\PartnerOffer\OperatorEnum;
use Illuminate\Database\Seeder;

class PartnerOfferSeeder extends Seeder
{
    public function run(): void
    {
        $imagesPath = public_path('images/offers');

        $offers = [
            [
                'title' => 'Offer 1',
                'short_description' => 'Short description for offer 1',
                'description' => 'Detailed description for offer 1',
                'old_price' => 199.99,
                'new_price' => 99.99,
                'phone_number' => '01000000001',
                'operator' => OperatorEnum::VODAFONE,
                'image' => $imagesPath . '/offer 1.png',
            ],
            [
                'title' => 'Offer 2',
                'short_description' => 'Short description for offer 2',
                'description' => 'Detailed description for offer 2',
                'old_price' => 299.99,
                'new_price' => 149.99,
                'phone_number' => '01000000002',
                'operator' => OperatorEnum::ETISALAT,
                'image' => $imagesPath . '/offer 2.png',
            ],
            [
                'title' => 'Offer 3',
                'short_description' => 'Short description for offer 3',
                'description' => 'Detailed description for offer 3',
                'old_price' => 399.99,
                'new_price' => 199.99,
                'phone_number' => '01000000003',
                'operator' => OperatorEnum::ORANGE,
                'image' => $imagesPath . '/offer 3.png',
            ],
            [
                'title' => 'Offer 4',
                'short_description' => 'Short description for offer 4',
                'description' => 'Detailed description for offer 4',
                'old_price' => 499.99,
                'new_price' => 249.99,
                'phone_number' => '01000000004',
                'operator' => OperatorEnum::WE,
                'image' => $imagesPath . '/offer 4.png',
            ],
        ];

        foreach ($offers as $data) {
            $imagePath = $data['image'];
            unset($data['image']);

            $offer = PartnerOffer::create([
                'partner_id' => 1,
                'title' => $data['title'],
                'short_description' => $data['short_description'],
                'description' => $data['description'],
                'old_price' => $data['old_price'],
                'new_price' => $data['new_price'],
                'phone_number' => $data['phone_number'],
                'operator' => $data['operator'],
            ]);

            if (file_exists($imagePath)) {
                $offer->addMedia($imagePath)
                    ->preservingOriginal()
                    ->toMediaCollection('header_image');
            }
        }
    }
}
