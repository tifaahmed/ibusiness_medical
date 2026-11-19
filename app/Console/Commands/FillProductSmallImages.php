<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Products with a large image but no small one get a COPY of the large one (the admin list and the apps draw the small one). Re-runnable. */
class FillProductSmallImages extends Command
{
    protected $signature = 'products:fill-small-images {--dry : Only list what would change}';

    protected $description = 'Copy the large image into an empty small image slot';

    public function handle(): int
    {
        $products = Product::query()
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'large_image'))
            ->whereDoesntHave('media', fn ($q) => $q->where('collection_name', 'small_image'))
            ->get();

        $failed = 0;

        foreach ($products as $product) {
            $this->line("#{$product->id} {$product->slug}");

            if ($this->option('dry')) {
                continue;
            }

            try {
                $product->ensureSmallImage();
            } catch (Throwable $e) {
                $failed++;
                Log::error('products:fill-small-images failed', ['product_id' => $product->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $this->error("#{$product->id}: {$e->getMessage()}");
            }
        }

        $this->info(($this->option('dry') ? 'Would fill ' : 'Filled ').$products->count().' product(s), failed '.$failed.'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
