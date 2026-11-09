<?php

namespace Tests\Feature;

use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\PointsPayments;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Caps on loyalty points: the system's per order, the system's per product, and a product's own limit (higher or lower). Spending is opt-in. */
class PointsCapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showAllPaymentMethods();
        config(['services.partner_api.key' => 'partner-test-key']);
        SiteSettings::put(ShopDelivery::COST, 0, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::PRICE, 0, Setting::TYPE_NUMBER);
        SiteSettings::put('loyalty_points_per_egp', 100, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    private function product(string $slug, float $price, ?int $max = null, bool $accepts = true): Product
    {
        return Product::query()->create(['name' => ['en' => $slug, 'ar' => $slug], 'slug' => $slug, 'old_price' => $price, 'new_price' => $price, 'points_payable' => $accepts, 'points_max' => $max]);
    }

    private function caps(int $order, int $product): void
    {
        SiteSettings::put('loyalty_points_max_per_order', $order, Setting::TYPE_NUMBER);
        SiteSettings::put('loyalty_points_max_per_product', $product, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    private function buy(array $items, User $user, array $extra = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', $extra + [
            'customer_full_name' => 'Mona Adel', 'customer_phone' => '01000000000', 'customer_address' => 'Flat 4, Giza', 'payment_type' => 'cod', 'items' => $items,
        ]);
    }

    private function rich(): User
    {
        $user = User::factory()->create();
        LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed', 'points' => 1000000, 'answered_at' => now()]);

        return $user;
    }

    /** @test */
    public function no_cap_by_default_and_points_are_never_spent_without_a_press(): void
    {
        $this->product('a', 300);
        $user = $this->rich();

        $this->assertEquals(0, $this->buy([['slug' => 'a', 'quantity' => 1]], $user)->assertCreated()->json('order.points_amount'));
        $this->assertEquals(300, $this->buy([['slug' => 'a', 'quantity' => 1]], $user, ['use_points' => true])->assertCreated()->json('order.points_amount'));
    }

    /** @test */
    public function the_system_cap_per_product_limits_the_pounds_a_product_may_take(): void
    {
        $this->caps(0, 5000); // 50 pounds per product
        $this->product('a', 300);
        $this->product('b', 40);
        $user = $this->rich();

        $res = $this->buy([['slug' => 'a', 'quantity' => 1], ['slug' => 'b', 'quantity' => 1]], $user, ['use_points' => true])->assertCreated();
        $this->assertEquals(50 + 40, $res->json('order.points_amount'));
        $this->assertEquals(9000, $res->json('order.points_used'));
    }

    /** @test */
    public function a_product_own_limit_beats_the_system_one_higher_or_lower(): void
    {
        $this->caps(0, 5000);
        $this->product('high', 300, 20000); // 200 pounds although the system says 50
        $this->product('low', 300, 1000);   // 10 pounds although the system says 50
        $user = $this->rich();

        $this->assertEquals(200, $this->buy([['slug' => 'high', 'quantity' => 1]], $user, ['use_points' => true])->json('order.points_amount'));
        $this->assertEquals(10, $this->buy([['slug' => 'low', 'quantity' => 1]], $user, ['use_points' => true])->json('order.points_amount'));
    }

    /** @test */
    public function the_per_order_cap_limits_the_whole_order_on_top_of_the_product_caps(): void
    {
        $this->caps(7000, 0); // 70 pounds per order
        $this->product('a', 300, 20000);
        $this->product('b', 300);
        $user = $this->rich();

        $res = $this->buy([['slug' => 'a', 'quantity' => 1], ['slug' => 'b', 'quantity' => 1]], $user, ['use_points' => true])->assertCreated();
        $this->assertEquals(70, $res->json('order.points_amount'));
        $this->assertEquals(7000, $res->json('order.points_used'));
    }

    /** @test */
    public function the_eligible_helper_follows_the_same_rules(): void
    {
        $this->caps(6000, 0);
        $this->assertSame(60.0, PointsPayments::eligible([['amount' => 100.0, 'cap' => null], ['amount' => 100.0, 'cap' => null]]));
        $this->assertSame(30.0, PointsPayments::eligible([['amount' => 100.0, 'cap' => 3000]]));
    }

    /** @test */
    public function the_mobile_product_and_the_loyalty_summary_tell_the_caps(): void
    {
        $this->caps(7000, 5000);
        $this->product('own', 300, 1000);
        $this->product('sys', 300);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/loyalty')->assertOk()->assertJsonPath('data.points_max_per_order', 7000);
        $list = collect($this->getJson('/api/v1/mobile/products')->assertOk()->json('data'))->keyBy('slug');
        $this->assertSame(1000, $list['own']['points_max']);
        $this->assertSame(5000, $list['sys']['points_max']);
    }
}
