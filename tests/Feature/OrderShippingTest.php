<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\OrderProduct;
use App\Models\Setting;
use App\Models\ShippingGroup;
use App\Models\Store;
use App\Models\StoreShippingRate;
use App\Support\OrderShipping;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Per-store / shared-warehouse / single-price shipping. Run on a throwaway database only (name ends in _test). */
class OrderShippingTest extends TestCase
{
    use RefreshDatabase;

    private function line(Store $store, float $total): OrderProduct
    {
        return new OrderProduct(['store_id' => $store->id, 'store_name' => ['ar' => $store->title, 'en' => $store->title], 'line_total' => $total, 'quantity' => 1, 'new_price' => $total]);
    }

    private function store(string $title, array $attrs = []): Store
    {
        return Store::query()->create(['title' => ['ar' => $title, 'en' => $title], 'slug' => $title] + $attrs);
    }

    private array $shop = ['cost' => 0.0, 'price' => 20.0, 'threshold' => null];

    public function test_each_store_is_its_own_parcel_with_its_own_price_and_governorate_override(): void
    {
        $gov = Governorate::query()->create(['name' => ['ar' => 'الجيزة', 'en' => 'Giza']]);
        $a = $this->store('a', ['shipping_price' => 50]);
        $b = $this->store('b', ['shipping_price' => 30, 'free_shipping_threshold' => 100]);
        StoreShippingRate::query()->create(['store_id' => $a->id, 'governorate_id' => $gov->id, 'price' => 70]);

        $plan = OrderShipping::plan(collect([$this->line($a, 60), $this->line($b, 150)]), 'الجيزة', $this->shop);

        $this->assertCount(2, $plan);
        $this->assertSame(70.0, $plan[0]['delivery_price']);   // governorate override
        $this->assertSame(0.0, $plan[1]['delivery_price']);    // b's own part reached its free amount
        $this->assertSame(70.0, OrderShipping::totals($plan)['delivery_price']);
    }

    public function test_a_store_without_a_price_falls_back_to_the_shop_price(): void
    {
        $plan = OrderShipping::plan(collect([$this->line($this->store('c'), 10)]), null, $this->shop);

        $this->assertSame(20.0, $plan[0]['delivery_price']);
        $this->assertSame('shop', $plan[0]['price_source']);
    }

    public function test_stores_sharing_a_warehouse_ship_as_one_parcel(): void
    {
        $group = ShippingGroup::query()->create(['name' => 'wh', 'price' => 60]);
        $a = $this->store('a', ['shipping_group_id' => $group->id, 'shipping_price' => 10]);
        $b = $this->store('b', ['shipping_group_id' => $group->id]);

        $plan = OrderShipping::plan(collect([$this->line($a, 10), $this->line($b, 10)]), null, $this->shop);

        $this->assertCount(1, $plan);
        $this->assertSame(60.0, $plan[0]['delivery_price']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $plan[0]['store_ids']);
    }

    public function test_the_single_shipping_switch_makes_one_parcel_at_one_price(): void
    {
        foreach (['shop_single_shipping_enabled' => ['1', 'boolean'], 'shop_single_shipping_price' => ['70', 'number']] as $slug => [$value, $type]) {
            Setting::query()->updateOrCreate(['slug' => $slug], ['name' => $slug, 'value' => $value, 'value_type' => $type]);
        }
        SiteSettings::forget();
        $a = $this->store('a', ['shipping_price' => 5]);
        $b = $this->store('b');

        $plan = OrderShipping::plan(collect([$this->line($a, 10), $this->line($b, 10)]), null, $this->shop);

        $this->assertCount(1, $plan);
        $this->assertSame(70.0, $plan[0]['delivery_price']);
        $this->assertSame('single', $plan[0]['price_source']);
    }
}
