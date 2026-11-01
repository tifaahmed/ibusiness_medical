<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The store list narrows by category, offer, shipping, presence of things and dates; garbage values filter nothing. */
class StoreListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        foreach (['manage stores', 'manage own stores'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $u->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $u;
    }

    private function ids(array $query): array
    {
        $res = $this->actingAs($this->admin())->withoutMiddleware()->get(route('admin.store.list', $query))->assertOk();

        return collect($res->viewData('page')['props']['stores']['data'])->pluck('id')->sort()->values()->all();
    }

    public function test_filters_narrow_the_list(): void
    {
        $cat = StoreCategory::create(['name' => ['ar' => 'صيدليات', 'en' => 'Pharmacies']]);
        $a = Store::create(['title' => ['ar' => 'متجر ألف', 'en' => 'Alpha'], 'online_only' => true, 'offer_percent_from' => 10, 'offer_percent_to' => 20]);
        $b = Store::create(['title' => ['ar' => 'متجر باء', 'en' => 'Beta'], 'online_only' => false, 'offer_percent_from' => 5.5]);
        $a->categories()->attach($cat->id);

        $this->assertSame([$a->id, $b->id], $this->ids([]));
        $this->assertSame([$b->id], $this->ids(['search' => 'Beta']));
        $this->assertSame([$a->id], $this->ids(['category_id' => $cat->id]));
        $this->assertSame([$b->id], $this->ids(['category_presence' => 'without']));
        $this->assertSame([$a->id], $this->ids(['store_type' => 'online']));
        $this->assertSame([$b->id], $this->ids(['store_type' => 'physical']));
        $this->assertSame([$b->id], $this->ids(['store_type' => 'physical_only']));
        // An online store with no branches is never a "physical store with no branches".
        $this->assertSame([$b->id], $this->ids(['branches_presence' => 'without']));
        $this->assertSame([$a->id], $this->ids(['store_type' => 'online', 'branches_presence' => 'without']));
        $b->update(['supports_shipping' => true]);
        $this->assertSame([$b->id], $this->ids(['store_type' => 'physical_online']));
        $this->assertSame([$b->id], $this->ids(['branches_max' => 0]));
        $this->assertSame([$b->id], $this->ids(['offer_format' => 'decimal']));
        $this->assertSame([$a->id], $this->ids(['offer_format' => 'round']));
        $this->assertSame([$a->id, $b->id], $this->ids(['category_id' => 'x; drop', 'online_only' => 'maybe']));
    }

    public function test_every_filter_builds_valid_sql(): void
    {
        foreach (['category_presence', 'tags_presence', 'sales_presence', 'managers_presence', 'branches_presence', 'products_presence', 'offer_presence', 'contract_presence', 'logo_presence', 'coupons_presence', 'website_presence', 'app_presence', 'social_presence'] as $k) {
            foreach (['with', 'without'] as $v) {
                $this->ids([$k => $v]);
            }
        }
        foreach (['governorate', 'city', 'either', 'both'] as $v) {
            $this->ids(['branches_missing' => $v]);
        }
        $this->ids(['seo_missing' => 'yes', 'offer_min' => 5, 'offer_max' => 50, 'contracted_from' => '2026-01-01', 'created_to' => '2027-01-01', 'supports_shipping' => 'no', 'ships_everywhere' => 'yes', 'shipping_governorate_id' => 1, 'tag_id' => 1, 'sales_id' => 1, 'created_by' => 1]);
        $this->assertTrue(true);
    }

    private function matrix(): array
    {
        $gov = \App\Models\Governorate::query()->first() ?? \App\Models\Governorate::create(['name' => ['ar' => 'القاهرة', 'en' => 'Cairo']]);
        $mk = fn (string $n, array $a) => Store::create(['title' => ['ar' => $n, 'en' => $n], ...$a]);
        $o = $mk('Online', ['online_only' => true, 'supports_shipping' => true, 'ships_everywhere' => true]);
        $p = $mk('Physical', ['online_only' => false, 'supports_shipping' => false]);
        $h = $mk('Hybrid', ['online_only' => false, 'supports_shipping' => true, 'ships_everywhere' => false]);
        $n = $mk('NoShipWide', ['online_only' => false, 'supports_shipping' => false, 'ships_everywhere' => true]); // stale flag: does not ship
        $p->branches()->create(['name' => ['ar' => 'ف', 'en' => 'b'], 'governorate_id' => $gov->id]);
        $h->branches()->create(['name' => ['ar' => 'ف', 'en' => 'b']]); // no governorate/city
        $h->shippingGovernorates()->attach($gov->id);

        return compact('o', 'p', 'h', 'n', 'gov');
    }

    public function test_the_four_types_and_shipping_filters(): void
    {
        ['o' => $o, 'p' => $p, 'h' => $h, 'n' => $n, 'gov' => $gov] = $this->matrix();

        $this->assertSame([$o->id], $this->ids(['store_type' => 'online']));
        $this->assertSame([$p->id, $h->id, $n->id], $this->ids(['store_type' => 'physical']));
        $this->assertSame([$p->id, $n->id], $this->ids(['store_type' => 'physical_only']));
        $this->assertSame([$h->id], $this->ids(['store_type' => 'physical_online']));

        // Shipping filters never show a store that does not ship, even with a stale ships_everywhere flag.
        $this->assertSame([$o->id], $this->ids(['ships_everywhere' => 'yes']));
        $this->assertSame([$o->id, $h->id], $this->ids(['shipping_governorate_id' => $gov->id]));
        $this->assertSame([$p->id, $n->id], $this->ids(['supports_shipping' => 'no']));
    }

    public function test_online_stores_without_branches_are_never_missing_anything(): void
    {
        ['o' => $o, 'p' => $p, 'h' => $h, 'n' => $n] = $this->matrix();

        $this->assertSame([$n->id], $this->ids(['branches_presence' => 'without']));   // only a physical store with no branch
        $this->assertSame([$p->id, $h->id], $this->ids(['branches_presence' => 'with']));
        $this->assertSame([$p->id, $h->id], $this->ids(['branches_missing' => 'either'])); // both have no city
        $this->assertSame([$h->id], $this->ids(['branches_missing' => 'both']));
        $this->assertNotContains($o->id, $this->ids(['branches_min' => 0]));            // branch-count filters are for physical stores
        $this->assertSame([$p->id, $h->id], $this->ids(['branches_min' => 1]));

        // Switching to Online with stale branch filters in the URL: they are dropped, results and echoed filters agree.
        $res = $this->actingAs($this->admin())->withoutMiddleware()
            ->get(route('admin.store.list', ['store_type' => 'online', 'branches_presence' => 'without', 'branches_missing' => 'both', 'governorate_id' => 5, 'city_id' => 9, 'branches_min' => 3]))->assertOk();
        $props = $res->viewData('page')['props'];
        $this->assertSame([$o->id], collect($props['stores']['data'])->pluck('id')->all());
        foreach (['branches_presence', 'branches_missing', 'governorate_id', 'city_id', 'branches_min'] as $k) {
            $this->assertSame('', $props['filters'][$k]);
        }
    }
}
