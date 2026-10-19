<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Store;
use App\Models\StoreBranch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills a store's page with obviously-fake test data, so every part of the
 * store screen (website and app) can be seen drawn at once.
 *
 *     php artisan stores:fill-demo dktor-bynz            # only fields that are EMPTY
 *     php artisan stores:fill-demo dktor-bynz --overwrite # replace what is there too
 *
 * Never touches the store's images (logo, header, gallery files) or its
 * categories: those are uploads, not text. Run `--dry` first to see what
 * would change. Meant for a staging copy or a store made for testing — it
 * writes to the real row, which is why it fills only blanks unless told to.
 */
class FillDemoStore extends Command
{
    protected $signature = 'stores:fill-demo {slug : The store\'s slug} {--overwrite : Replace fields that already have a value} {--dry : Show what would change and write nothing}';

    protected $description = 'Fill a store with test data (blank fields only, unless --overwrite)';

    public function handle(): int
    {
        $store = Store::query()->where('slug', $this->argument('slug'))->first();

        if ($store === null) {
            $this->error("No store with the slug \"{$this->argument('slug')}\" in this database.");

            return self::FAILURE;
        }

        $overwrite = (bool) $this->option('overwrite');
        $dry = (bool) $this->option('dry');
        $changes = [];

        $text = fn (string $en, string $ar) => ['en' => $en, 'ar' => $ar];

        $wanted = [
            'short_description' => $text('Test store — specialty coffee and gifts', 'متجر تجريبي — قهوة مختصة وهدايا'),
            'description' => $text(
                '<p>This is <b>test data</b> filling every field of the store page.</p><p>Fresh roasted beans, brewing gear and gift boxes for cardholders.</p>',
                '<p>هذه <b>بيانات تجريبية</b> لملء كل خانة في صفحة المتجر.</p><p>بن محمص طازج وأدوات تحضير وصناديق هدايا لحاملي الكارت.</p>',
            ),
            'youtube_link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'websites' => ['https://example.com', 'https://shop.example.com'],
            'social_links' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com/example'],
                ['platform' => 'instagram', 'url' => 'https://instagram.com/example'],
                ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@example'],
            ],
            'coupons' => [
                ['code' => 'TEST10', 'title' => $text('10% off your first order', 'خصم 10% على أول طلب'), 'expires_at' => now()->addMonths(3)->toDateString()],
                ['code' => 'OLD50', 'title' => $text('Expired coupon (must NOT show)', 'كوبون منتهي (يجب ألا يظهر)'), 'expires_at' => now()->subDay()->toDateString()],
            ],
            'supports_shipping' => true,
            'ships_everywhere' => true,
            'offer_percent_from' => 5,
            'offer_percent_to' => 15,
            'app_store_url' => 'https://apps.apple.com/app/id000000000',
            'google_play_url' => 'https://play.google.com/store/apps/details?id=com.example.test',
        ];

        foreach ($wanted as $field => $value) {
            $current = $store->getRawOriginal($field);
            $isBlank = $current === null || $current === '' || $current === '[]' || $current === '{}' || $current === '{"en":"","ar":""}' || $current === '{"ar":"","en":""}';

            if ($field === 'online_only' || (! $isBlank && ! $overwrite)) {
                continue;
            }

            $changes[$field] = $value;
        }

        $this->table(['Field', 'Will be set'], collect($changes)->map(fn ($v, $k) => [$k, mb_strimwidth(json_encode($v, JSON_UNESCAPED_UNICODE), 0, 90, '…')])->values()->all());

        $city = City::query()->orderBy('id')->first();
        $addBranches = $city !== null && ($overwrite || ! $store->branches()->exists());

        $this->line($addBranches ? 'Will add 2 test branches ('.($city?->governorate_id ? 'first governorate/city in this database' : '').').' : 'Branches left as they are.');

        if ($dry) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($store, $changes, $addBranches, $city) {
            foreach ($changes as $field => $value) {
                if (in_array($field, $store->getTranslatableAttributes(), true)) {
                    $store->setTranslations($field, $value);
                } else {
                    $store->{$field} = $value;
                }
            }
            $store->save();

            if ($addBranches) {
                foreach ([
                    ['name' => 'Test branch — Nasr City', 'address' => '12 Abbas El Akkad St', 'area' => 'Nasr City', 'latitude' => 30.0561, 'longitude' => 31.3301, 'phone' => [['number' => '01012345678', 'type' => 'phone'], ['number' => '01012345678', 'type' => 'whatsapp']]],
                    ['name' => 'Test branch — Maadi', 'address' => '9 Road 9', 'area' => 'Maadi', 'latitude' => 29.9602, 'longitude' => 31.2569, 'phone' => [['number' => '01122334455', 'type' => 'phone']]],
                ] as $branch) {
                    StoreBranch::query()->create($branch + [
                        'store_id' => $store->id,
                        'governorate_id' => $city->governorate_id,
                        'city_id' => $city->id,
                    ]);
                }
            }
        });

        $this->info("Store \"{$store->slug}\" filled with test data.");
        $this->line('Images (logo, header, gallery, videos) are uploads: add them in the dashboard.');

        return self::SUCCESS;
    }
}
