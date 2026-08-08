<?php

namespace App\Console\Commands;

use App\Models\Offer;
use Illuminate\Console\Command;

class CopyOfferImagesToMobile extends Command
{
    protected $signature = 'offer:copy-images-to-mobile';

    protected $description = 'Copy each offer\'s image and thumbnail to mobile_image and mobile_thumbnail collections';

    public function handle(): void
    {
        $offers = Offer::all();
        $bar = $this->output->createProgressBar($offers->count());
        $bar->start();

        $copied = 0;
        $skipped = 0;

        $pairs = [
            'image' => 'mobile_image',
            'thumbnail' => 'mobile_thumbnail',
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
