<?php

namespace Tests\Feature;

use App\Enums\Order\OrderStatusEnum;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Loyalty points as a checkout discount: only products that accept them, whole pounds, before the wallet, given back on failure. */
class PointsPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showAllPaymentMethods();
        config(['services.partner_api.key' => 'partner-test-key']);
        SiteSettings::put(ShopDelivery::COST, 20, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::PRICE, 20, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::THRESHOLD, '', Setting::TYPE_NUMBER);
        SiteSettings::put('loyalty_points_per_egp', 100, Setting::TYPE_NUMBER);
        SiteSettings::forget();
    }

    private function product(string $slug, float $price, bool $points): Product
    {
        return Product::query()->create(['name' => ['en' => $slug, 'ar' => $slug], 'slug' => $slug, 'old_price' => $price, 'new_price' => $price, 'points_payable' => $points]);
    }

    private function points(User $user, int $n): void
    {
        LoyaltyTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed'.uniqid(), 'points' => $n, 'answered_at' => now()]);
    }

    private function order(array $items, ?User $as = null, array $extra = []): \Illuminate\Testing\TestResponse
    {
        if ($as) {
            Sanctum::actingAs($as);
        }

        return $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', $extra + [
            'customer_full_name' => 'Mona Adel', 'customer_phone' => '01000000000', 'customer_address' => 'Flat 4, Giza', 'payment_type' => 'cod', 'items' => $items,
            'use_points' => true,
        ]);
    }

    /** @test */
    public function points_discount_only_the_products_that_accept_them_in_whole_pounds_then_the_wallet_pays_the_rest(): void
    {
        $this->product('accepts', 100, true);
        $this->product('plain', 50, false);
        $user = User::factory()->create();
        $this->points($user, 25050); // 250.5 pounds worth, but only 100 of the basket accepts points
        WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'w', 'amount' => 30]);

        $res = $this->order([['slug' => 'accepts', 'quantity' => 1], ['slug' => 'plain', 'quantity' => 1]], $user)->assertCreated();

        // 150 of products + 20 delivery = 170. Points take 100 (10 000 points); the wallet takes 30; 40 is left (incl. delivery).
        $this->assertEquals(170, $res->json('order.total_amount'));
        $this->assertEquals(100, $res->json('order.points_amount'));
        $this->assertEquals(10000, $res->json('order.points_used'));
        $this->assertEquals(30, $res->json('order.wallet_amount'));
        $this->assertEquals(40, $res->json('order.amount_due'));
        $this->assertEquals(15050, LoyaltyTransaction::balanceOf($user->id));
        $this->assertEquals(0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function a_small_balance_spends_whole_pounds_only_and_turning_it_off_spends_nothing(): void
    {
        $this->product('accepts', 100, true);
        $user = User::factory()->create();
        $this->points($user, 1299); // 12.99 -> 12 pounds = 1200 points

        $res = $this->order([['slug' => 'accepts', 'quantity' => 1]], $user)->assertCreated();
        $this->assertEquals(12, $res->json('order.points_amount'));
        $this->assertEquals(1200, $res->json('order.points_used'));
        $this->assertEquals(99, LoyaltyTransaction::balanceOf($user->id));

        $off = User::factory()->create();
        $this->points($off, 99999);
        $res = $this->order([['slug' => 'accepts', 'quantity' => 1]], $off, ['use_points' => false])->assertCreated();
        $this->assertEquals(0, $res->json('order.points_amount'));
        $this->assertEquals(99999, LoyaltyTransaction::balanceOf($off->id));
    }

    /** @test */
    public function the_website_must_ask_for_points_but_the_app_gets_them_by_default(): void
    {
        $this->product('accepts', 100, true);
        $user = User::factory()->create();
        $this->points($user, 5000);
        Sanctum::actingAs($user);
        $body = ['customer_full_name' => 'Mona Adel', 'customer_phone' => '01000000000', 'customer_address' => 'Flat 4, Giza', 'payment_type' => 'cod', 'items' => [['slug' => 'accepts', 'quantity' => 1]]];

        $web = $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', $body)->assertCreated();
        $this->assertEquals(0, $web->json('order.points_amount'));

        $app = $this->postJson('/api/v1/mobile/orders', $body)->assertCreated();
        $this->assertEquals(50, $app->json('order.points_amount'));
    }

    /** @test */
    public function a_guest_spends_no_points(): void
    {
        $this->product('accepts', 100, true);

        $this->assertEquals(0, $this->order([['slug' => 'accepts', 'quantity' => 1]])->assertCreated()->json('order.points_amount'));
    }

    /** @test */
    public function points_and_wallet_covering_the_products_leave_only_delivery_and_a_failed_order_gives_the_points_back_once(): void
    {
        $this->product('accepts', 100, true);
        $user = User::factory()->create();
        $this->points($user, 6000); // 60
        WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'w', 'amount' => 70]);

        $res = $this->order([['slug' => 'accepts', 'quantity' => 1]], $user)->assertCreated();
        $this->assertEquals(60, $res->json('order.points_amount'));
        $this->assertEquals(40, $res->json('order.wallet_amount')); // points took 60 of the 100; the wallet needs 40, delivery stays real money
        $this->assertEquals(20, $res->json('order.amount_due'));
        $this->assertEquals(0, LoyaltyTransaction::balanceOf($user->id));
        $this->assertEquals(30, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function a_failed_order_returns_points_as_points_and_money_to_the_wallet_once(): void
    {
        $this->product('accepts', 100, true);
        $user = User::factory()->create();
        $this->points($user, 4000); // 40 pounds
        WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'w', 'amount' => 10]);

        $code = $this->order([['slug' => 'accepts', 'quantity' => 1]], $user)->assertCreated()->json('order.order_code');
        $this->assertEquals(0, LoyaltyTransaction::balanceOf($user->id));

        $order = Order::query()->where('order_code', $code)->firstOrFail();
        $order->update(['order_status' => OrderStatusEnum::FAILED]);
        $this->assertEquals(4000, LoyaltyTransaction::balanceOf($user->id));
        $this->assertEquals(10, WalletTransaction::balanceOf($user->id)); // wallet part comes back; nothing else was paid in real money

        $order->update(['order_status' => OrderStatusEnum::FAILED]);
        $this->assertEquals(4000, LoyaltyTransaction::balanceOf($user->id)); // never twice
    }
}
