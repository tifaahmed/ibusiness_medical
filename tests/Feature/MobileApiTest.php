<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\FacilityBranch;
use App\Models\FacilityType;
use App\Models\Membership;
use App\Models\User;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lean `/api/v1/mobile/*` endpoints behind the Deilar mobile app. These
 * pin the contract that makes them fast: each response carries only what its
 * screen draws, ordering/filtering happens in SQL, and repeats are free (304).
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function facility(string $name, array $attrs = []): Facility
    {
        $type = FacilityType::firstOrCreate(['slug' => 'labs'], ['name' => ['en' => 'Labs', 'ar' => 'معامل']]);

        return Facility::create(array_merge(['name' => ['en' => $name, 'ar' => $name], 'discount_percent' => 20, 'facility_type_id' => $type->id], $attrs));
    }

    private function branch(Facility $facility, float $lat, float $lng): FacilityBranch
    {
        return FacilityBranch::create([
            'facility_id' => $facility->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'name' => ['en' => 'Main', 'ar' => 'Main'],
            'address' => ['en' => '1 Street', 'ar' => '1 Street'],
            'phone' => ['0233925058'],
        ]);
    }

    /** @test */
    public function the_facility_list_carries_only_what_a_card_draws(): void
    {
        $this->branch($this->facility('Care Lab'), 30.0, 31.0);

        $response = $this->getJson('/api/v1/mobile/facilities')->assertOk();

        $response->assertJsonStructure([
            'data' => [['id', 'slug', 'name', 'logo', 'discount', 'type', 'distance_km', 'branch' => ['governorate', 'city', 'address', 'lat', 'lng', 'phone', 'whatsapp']]],
            'has_more',
        ]);
        // The web directory ships these with every page; the app must not.
        foreach (['offers', 'facility_names', 'governorates', 'cities', 'facility_types', 'filters'] as $heavy) {
            $response->assertJsonMissingPath($heavy);
        }
        $response->assertJsonPath('data.0.branch.phone', '0233925058');
    }

    /** @test */
    public function facilities_come_back_nearest_first_with_their_distance(): void
    {
        $far = $this->facility('Far Clinic');
        $this->branch($far, 31.2, 29.9);          // Alexandria
        $near = $this->facility('Near Clinic');
        $this->branch($near, 30.05, 31.24);       // Cairo

        $response = $this->getJson('/api/v1/mobile/facilities?lat=30.04&lng=31.23')->assertOk();

        $response->assertJsonPath('data.0.slug', $near->slug);
        $response->assertJsonPath('data.1.slug', $far->slug);
        $this->assertLessThan(5, $response->json('data.0.distance_km'));
        $this->assertGreaterThan(100, $response->json('data.1.distance_km'));
    }

    /** @test */
    public function a_radius_drops_facilities_beyond_it(): void
    {
        $this->branch($this->facility('Far Clinic'), 31.2, 29.9);
        $near = $this->facility('Near Clinic');
        $this->branch($near, 30.05, 31.24);

        $response = $this->getJson('/api/v1/mobile/facilities?lat=30.04&lng=31.23&radius_km=10')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', $near->slug);
    }

    /** @test */
    public function a_radius_without_a_position_is_rejected(): void
    {
        $this->getJson('/api/v1/mobile/facilities?radius_km=10')->assertStatus(422);
    }

    /** @test */
    public function an_unchanged_response_is_answered_with_an_empty_304(): void
    {
        $this->branch($this->facility('Care Lab'), 30.0, 31.0);

        $first = $this->getJson('/api/v1/mobile/facilities')->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->getJson('/api/v1/mobile/facilities', ['If-None-Match' => $etag])
            ->assertStatus(304)
            ->assertNoContent(304);
    }

    /** @test */
    public function map_pins_are_bare_and_nearest_first(): void
    {
        $this->branch($this->facility('Far Clinic'), 31.2, 29.9);
        $this->branch($this->facility('Near Clinic'), 30.05, 31.24);

        $response = $this->getJson('/api/v1/mobile/map-pins?lat=30.04&lng=31.23')->assertOk();

        $response->assertJsonStructure(['data' => [['id', 'lat', 'lng', 'name']], 'has_more']);
        $response->assertJsonPath('data.0.name', 'Near Clinic');
        $this->assertSame(['id', 'lat', 'lng', 'name'], array_keys($response->json('data.0')));
    }

    /** @test */
    public function facility_detail_pages_its_branches(): void
    {
        $facility = $this->facility('Chain');
        foreach (range(1, 12) as $i) {
            $this->branch($facility, 30 + $i / 100, 31);
        }

        $detail = $this->getJson("/api/v1/mobile/facilities/{$facility->slug}")->assertOk();
        $detail->assertJsonCount(10, 'branches');
        $detail->assertJsonPath('has_more', true);

        $more = $this->getJson("/api/v1/mobile/facilities/{$facility->slug}/branches?page=2")->assertOk();
        $more->assertJsonCount(2, 'branches');
        $more->assertJsonPath('has_more', false);
    }

    /** @test */
    public function products_never_expose_cost_and_hidden_ones_stay_out_of_the_list(): void
    {
        $visible = Product::create([
            'name' => ['en' => 'Honey', 'ar' => 'عسل'], 'old_price' => 100, 'new_price' => 80,
            'cost_price' => 50, 'profit_price' => 30, 'admin_note' => 'secret',
            'is_visible' => true, 'is_accessible' => true, 'is_purchasable' => true,
        ]);
        Product::create([
            'name' => ['en' => 'Hidden', 'ar' => 'مخفي'], 'new_price' => 10,
            'is_visible' => false, 'is_accessible' => true, 'is_purchasable' => true,
        ]);

        $response = $this->getJson('/api/v1/mobile/products')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.price', 80);
        $response->assertJsonPath('data.0.old_price', 100);
        $response->assertJsonPath('data.0.discount', 20);
        $this->assertStringNotContainsString('cost', $response->getContent());
        $this->assertStringNotContainsString('secret', $response->getContent());

        $this->getJson("/api/v1/mobile/products/{$visible->slug}")->assertOk()->assertJsonStructure(['description', 'gallery']);
    }

    /** @test */
    public function a_closed_product_is_a_404(): void
    {
        $closed = Product::create([
            'name' => ['en' => 'Closed', 'ar' => 'مغلق'], 'new_price' => 10,
            'is_visible' => true, 'is_accessible' => false, 'is_purchasable' => false,
        ]);

        $this->getJson("/api/v1/mobile/products/{$closed->slug}")->assertNotFound();
    }

    /** @test */
    public function a_card_is_shown_by_number_without_contact_details(): void
    {
        $user = User::factory()->create(['name' => 'Sara Ali', 'email' => 'sara@example.test', 'phone' => '01012345678']);
        $membership = Membership::factory()->create(['user_id' => $user->id, 'is_visible' => true]);

        $response = $this->getJson("/api/v1/mobile/card/{$membership->membership_number}")->assertOk();

        $response->assertJsonPath('number', $membership->membership_number);
        $response->assertJsonPath('holder', 'Sara Ali');
        $response->assertJsonStructure(['number', 'active', 'valid_from', 'valid_to', 'holder', 'members']);
        $this->assertStringNotContainsString('sara@example.test', $response->getContent());
        $this->assertStringNotContainsString('01012345678', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /** @test */
    public function an_unknown_or_hidden_card_number_is_a_404(): void
    {
        $hidden = Membership::factory()->create(['is_visible' => false]);

        $this->getJson('/api/v1/mobile/card/NOPE-0000')->assertNotFound();
        $this->getJson("/api/v1/mobile/card/{$hidden->membership_number}")->assertNotFound();
    }

    /** @test */
    public function phone_sign_in_rejects_a_bad_number_and_a_code_nobody_asked_for(): void
    {
        $this->postJson('/api/v1/mobile/auth/otp', ['phone' => 'abc'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_phone');

        $this->postJson('/api/v1/mobile/auth/otp/verify', ['phone' => '01099999999', 'code' => '0000'])
            ->assertStatus(422)
            ->assertJsonMissingPath('data.token');
    }

    private function basketProduct(float $price): Product
    {
        return Product::create([
            'name' => ['en' => 'Honey', 'ar' => 'عسل'], 'new_price' => $price,
            'is_visible' => true, 'is_accessible' => true, 'is_purchasable' => true,
        ]);
    }

    private function orderBody(Product $product, array $extra = []): array
    {
        return array_merge([
            'customer_full_name' => 'Sara Ali',
            'customer_phone' => '01012345678',
            'customer_address' => '1 Street, Cairo',
            'payment_type' => 'cod',
            'items' => [['slug' => $product->slug, 'quantity' => 2]],
        ], $extra);
    }

    /** @test */
    public function an_app_order_is_priced_and_delivered_by_the_server_not_the_phone(): void
    {
        $product = $this->basketProduct(100);

        // The phone claims free delivery, a fake IP and another source: none of it is believed.
        $response = $this->postJson('/api/v1/mobile/orders', $this->orderBody($product, [
            'delivery_price' => 0, 'delivery_cost' => 0, 'free_delivery_threshold' => 0,
            'ip_address' => '1.2.3.4', 'source' => 'storefront',
        ]))->assertCreated();

        $response->assertJsonPath('order.delivery_price', 35);
        $response->assertJsonPath('order.total_amount', 235); // 2 x 100 + 35 delivery
        $this->assertDatabaseHas('orders', ['source' => 'mobile_app']);
        $this->assertDatabaseMissing('orders', ['ip_address' => '1.2.3.4']);
    }

    /** @test */
    public function a_big_enough_app_basket_ships_free(): void
    {
        $product = $this->basketProduct(300);

        $this->postJson('/api/v1/mobile/orders', $this->orderBody($product))
            ->assertCreated()
            ->assertJsonPath('order.delivery_price', 0)
            ->assertJsonPath('order.total_amount', 600);
    }

    /** @test */
    public function an_app_order_can_be_looked_up_by_its_code(): void
    {
        $code = $this->postJson('/api/v1/mobile/orders', $this->orderBody($this->basketProduct(100)))
            ->assertCreated()->json('order.order_code');

        $this->getJson("/api/v1/mobile/orders/{$code}")->assertOk()->assertJsonPath('order.order_code', $code);
        $this->getJson('/api/v1/mobile/orders/NOSUCH00')->assertNotFound();
    }
}
