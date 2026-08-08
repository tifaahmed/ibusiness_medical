<?php

namespace App\Console\Commands;

use App\Models\Facility;
use Illuminate\Console\Command;

class CopyFacilityImagesToMobile extends Command
{
    protected $signature = 'facility:copy-images-to-mobile';

    protected $description = 'Copy each facility\'s logo and cover image to mobile_logo and mobile_image collections';

    public function handle(): void
    {
        $facilities = Facility::all();
        $bar = $this->output->createProgressBar($facilities->count());
        $bar->start();

        $copied = 0;
        $skipped = 0;

        $pairs = [
            'image' => 'mobile_image',
            'logo' => 'mobile_logo',
        ];

        foreach ($facilities as $facility) {
            foreach ($pairs as $source => $target) {
                $media = $facility->getFirstMedia($source);

                if ($media && !$facility->getFirstMedia($target)) {
                    $media->copy($facility, $target);
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
