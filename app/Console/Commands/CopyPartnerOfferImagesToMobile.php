<?php

namespace App\Console\Commands;

use App\Models\PartnerOffer;
use Illuminate\Console\Command;

class CopyPartnerOfferImagesToMobile extends Command
{
    protected $signature = 'partner-offer:copy-images-to-mobile';

    protected $description = 'Copy each partner offer\'s header_image and small_image to mobile_header_image and mobile_small_image collections';

    public function handle(): void
    {
        $offers = PartnerOffer::all();
        $bar = $this->output->createProgressBar($offers->count());
        $bar->start();

        $copied = 0;
        $skipped = 0;

        $pairs = [
            'header_image' => 'mobile_header_image',
            'small_image' => 'mobile_small_image',
        ];

        foreach ($offers as $offer) {
            foreach ($pairs as $source => $target) {
                $media = $offer->getFirstMedia($source);

                if ($media && !$offer->getFirstMedia($target)) {
                    $media->copy($offer, $target);
                    $copied++;
                } else {
                    $skipped++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! {$copied} images copied, {$skipped} skipped.");
    }
}
