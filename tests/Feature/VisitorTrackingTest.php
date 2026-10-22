<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\PageVisit;
use App\Models\Product;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Visitors: web (key-gated partner route) and apps (mobile route), enrichment
 * as more is revealed, the product "Visitors" tab, and the website import.
 */
class VisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create(['name' => ['en' => 'Honey', 'ar' => 'عسل'], 'slug' => 'honey', 'new_price' => 10]);
    }

    private function web(array $body, array $headers = [])
    {
        config(['services.partner_api.key' => 'k']);

        return $this->withHeaders(['X-Api-Key' => 'k'] + $headers)->postJson('/api/v1/partner/visits', $body + [
            'visitor_key' => 'web-visitor-0001',
            'page' => 'products.show',
        ]);
    }

    public function test_website_visit_is_recorded_with_the_visitors_own_ip_and_product(): void
    {
        $product = $this->product();

        $this->web([
            'ip_address' => '41.1.2.3',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Safari/604.1',
            'subject_type' => 'product',
            'subject_slug' => 'honey',
            'url' => 'https://deilar.com/ar/products/honey',
        ])->assertCreated();

        $visitor = Visitor::firstOrFail();
        $this->assertSame('web', $visitor->source);
        $this->assertSame('41.1.2.3', $visitor->ip_address);
        $this->assertSame('mobile', $visitor->device_type);
        $this->assertSame(1, $visitor->visits_count);
        $this->assertSame($product->id, PageVisit::firstOrFail()->product_id);
    }

    public function test_later_hits_enrich_the_same_visitor_and_never_wipe_what_is_known(): void
    {
        $this->web(['ip_address' => '41.1.2.3', 'latitude' => 30.04, 'longitude' => 31.23])->assertCreated();
        $this->web(['phone' => '01062587475', 'email' => 'a@b.co', 'name' => 'Sara'])->assertCreated();

        $this->assertSame(1, Visitor::count());
        $visitor = Visitor::first();
        $this->assertSame('01062587475', $visitor->phone);
        $this->assertSame(30.04, $visitor->latitude);
        $this->assertSame('41.1.2.3', $visitor->ip_address);
        $this->assertSame(2, $visitor->visits_count);
        $this->assertNull($visitor->user_id, 'a typed phone is a claim, never a verified member');
    }

    public function test_a_bearer_token_links_the_visitor_to_the_member(): void
    {
        $user = User::factory()->create(['phone' => '01062587475']);
        Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true]);
        $token = $user->createToken('t')->plainTextToken;

        $this->web([], ['Authorization' => 'Bearer '.$token])->assertCreated();

        $visitor = Visitor::first();
        $this->assertSame($user->id, $visitor->user_id);
        $this->assertNotNull($visitor->membership_id);
        $this->assertSame($user->id, PageVisit::first()->user_id);
    }

    public function test_the_app_route_needs_a_platform_and_uses_the_request_ip(): void
    {
        $this->postJson('/api/v1/mobile/visits', ['visitor_key' => 'app-visitor-0001', 'page' => 'home'])
            ->assertUnprocessable();

        $this->postJson('/api/v1/mobile/visits', ['visitor_key' => 'app-visitor-0001', 'page' => 'home', 'platform' => 'android', 'ip_address' => '1.1.1.1'])
            ->assertCreated();

        $visitor = Visitor::first();
        $this->assertSame('android', $visitor->source);
        $this->assertNotSame('1.1.1.1', $visitor->ip_address, 'an app caller cannot choose the IP it is recorded under');
    }

    public function test_partner_route_is_key_gated(): void
    {
        config(['services.partner_api.key' => 'k']);
        $this->postJson('/api/v1/partner/visits', ['visitor_key' => 'web-visitor-0001', 'page' => 'home'])->assertUnauthorized();
    }

    public function test_product_tab_lists_visitors_and_filters_by_source(): void
    {
        $product = $this->product();
        $this->web(['subject_type' => 'product', 'subject_slug' => 'honey', 'phone' => '0100'])->assertCreated();
        $this->postJson('/api/v1/mobile/visits', ['visitor_key' => 'ios-visitor-0001', 'platform' => 'ios', 'page' => 'product', 'subject_type' => 'product', 'subject_slug' => 'honey'])->assertCreated();

        $admin = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::findOrCreate('view visitors', 'web'));
        $admin->assignRole($role);

        $all = $this->actingAs($admin)->getJson(route('admin.product.visitors', $product->slug))->assertOk();
        $all->assertJsonPath('stats.views', 2)->assertJsonPath('stats.unique_visitors', 2);

        $ios = $this->actingAs($admin)->getJson(route('admin.product.visitors', $product->slug).'?source=ios')->assertOk();
        $ios->assertJsonPath('stats.views', 1)->assertJsonPath('visits.data.0.source', 'ios');

        $this->actingAs($admin)->get(route('admin.visitors.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.visitors.show', Visitor::first()))->assertOk();
    }

    public function test_import_copies_old_website_visits_once(): void
    {
        $product = $this->product();
        Schema::create('legacy_visits', function ($t) {
            $t->id();
            $t->string('page');
            $t->text('url');
            $t->string('locale')->nullable();
            $t->string('visitor_hash');
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->text('referrer')->nullable();
            $t->string('device')->nullable();
            $t->boolean('is_bot')->default(false);
            $t->dateTime('visited_at');
        });
        foreach ([1, 2] as $i) {
            DB::table('legacy_visits')->insert([
                'page' => 'products.show', 'url' => 'https://deilar.com/ar/products/honey?x=1', 'locale' => 'ar',
                'visitor_hash' => 'day'.$i, 'ip_address' => '41.1.1.1', 'user_agent' => 'Mozilla/5.0 Chrome', 'device' => 'desktop',
                'visited_at' => now()->subDays($i),
            ]);
        }

        $this->artisan('visits:import-website', ['--connection' => config('database.default'), '--table' => 'legacy_visits'])->assertSuccessful();
        $this->artisan('visits:import-website', ['--connection' => config('database.default'), '--table' => 'legacy_visits'])->assertSuccessful();

        $this->assertSame(2, PageVisit::count());
        $this->assertSame(1, Visitor::count(), 'same ip + agent on two days is one visitor');
        $this->assertSame(2, Visitor::first()->visits_count);
        $this->assertSame($product->id, PageVisit::first()->product_id);
    }

    public function test_gps_is_resolved_to_the_governorate_whose_border_holds_it(): void
    {
        $gov = \App\Models\Governorate::factory()->create();
        $gov->boundary = ['type' => 'Polygon', 'coordinates' => [[[31.0, 30.0], [32.0, 30.0], [32.0, 31.0], [31.0, 31.0], [31.0, 30.0]]]];
        $gov->save();

        $this->web(['latitude' => 30.5, 'longitude' => 31.5])->assertCreated();

        $this->assertSame($gov->id, Visitor::first()->governorate_id);
        $this->assertSame($gov->id, PageVisit::first()->governorate_id);
    }
}
