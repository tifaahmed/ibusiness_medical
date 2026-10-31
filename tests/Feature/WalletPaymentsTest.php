<?php

namespace Tests\Feature;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\MembershipPlan;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use App\Support\WalletPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Paying with the wallet: orders (products the admin allowed, never delivery), refunds into the wallet, and the yearly membership. */
class WalletPaymentsTest extends TestCase
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
        SiteSettings::forget();
    }

    private function product(string $slug, float $price, bool $payable): Product
    {
        return Product::query()->create(['name' => ['en' => $slug, 'ar' => $slug], 'slug' => $slug, 'old_price' => $price, 'new_price' => $price, 'wallet_payable' => $payable]);
    }

    private function wallet(User $user, float $amount): void
    {
        WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed'.uniqid(), 'amount' => $amount]);
    }

    private function order(array $items, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        if ($as) {
            Sanctum::actingAs($as);
        }

        return $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', [
            'customer_full_name' => 'Mona Adel', 'customer_phone' => '01000000000', 'customer_address' => 'Flat 4, Giza', 'payment_type' => 'cod', 'items' => $items,
        ]);
    }

    /** @test */
    public function the_wallet_cuts_the_bill_automatically_for_allowed_products_but_never_for_delivery(): void
    {
        $this->product('allowed', 100, true);
        $user = User::factory()->create();
        $this->wallet($user, 500);

        $res = $this->order([['slug' => 'allowed', 'quantity' => 2]], $user)->assertCreated();

        // 2 x 100 = 200 of products, +20 delivery. The wallet pays the 200; the 20 delivery is real money.
        $this->assertEquals(220, $res->json('order.total_amount'));
        $this->assertEquals(200, $res->json('order.wallet_amount'));
        $this->assertEquals(20, $res->json('order.amount_due'));
        $this->assertEquals(300, WalletTransaction::balanceOf($user->id));
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'wallet_amount' => 200, 'payment_status' => 'pending']);
    }

    /** @test */
    public function a_small_wallet_pays_what_it_has_and_a_product_not_allowed_is_never_paid_from_it(): void
    {
        $this->product('allowed', 100, true);
        $this->product('plain', 100, false);
        $user = User::factory()->create();
        $this->wallet($user, 60);

        $res = $this->order([['slug' => 'allowed', 'quantity' => 1], ['slug' => 'plain', 'quantity' => 1]], $user)->assertCreated();
        $this->assertEquals(60, $res->json('order.wallet_amount'));
        $this->assertEquals(160, $res->json('order.amount_due'));
        $this->assertEquals(0, WalletTransaction::balanceOf($user->id));

        $other = User::factory()->create();
        $this->wallet($other, 500);
        $res = $this->order([['slug' => 'plain', 'quantity' => 1]], $other)->assertCreated();
        $this->assertEquals(0, $res->json('order.wallet_amount'));
        $this->assertEquals(500, WalletTransaction::balanceOf($other->id));
    }

    /** @test */
    public function a_guest_pays_in_full(): void
    {
        $this->product('allowed', 100, true);

        $res = $this->order([['slug' => 'allowed', 'quantity' => 1]])->assertCreated();
        $this->assertEquals(0, $res->json('order.wallet_amount'));
        $this->assertEquals(120, $res->json('order.amount_due'));
    }

    /** @test */
    public function an_order_cancelled_or_failed_after_the_sale_sends_the_money_back_to_the_wallet_once(): void
    {
        $this->product('allowed', 100, true);
        $user = User::factory()->create();
        $this->wallet($user, 500);
        $code = $this->order([['slug' => 'allowed', 'quantity' => 2]], $user)->json('order.order_code');
        $this->assertEquals(300, WalletTransaction::balanceOf($user->id));

        $order = Order::query()->where('order_code', $code)->firstOrFail();
        $order->update(['order_status' => OrderStatusEnum::FAILED]);
        $this->assertEquals(500, WalletTransaction::balanceOf($user->id));
        $this->assertNotNull($order->fresh()->wallet_refunded_at);

        // Cancelling it as well must not pay twice.
        $order->update(['payment_status' => PaymentStatusEnum::CANCELED]);
        $this->assertEquals(500, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function money_paid_for_real_comes_back_to_the_wallet_too(): void
    {
        $this->product('allowed', 100, true);
        $user = User::factory()->create();
        $this->wallet($user, 50);
        $code = $this->order([['slug' => 'allowed', 'quantity' => 1]], $user)->json('order.order_code');
        $order = Order::query()->where('order_code', $code)->firstOrFail();
        // 120 total: 50 from the wallet, 70 paid for real, then the order is refused at the door.
        $order->update(['payment_status' => PaymentStatusEnum::ACCEPTED, 'total_paid' => 120]);
        $order->update(['order_status' => OrderStatusEnum::FAILED]);

        $this->assertEquals(120, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function the_yearly_membership_can_be_bought_with_the_wallet_when_it_holds_the_whole_price(): void
    {
        SiteSettings::put(MembershipPlan::PRICE, 600, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::MONTHS, 12, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $user = User::factory()->create();
        $card = Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_TRIAL, 'expiration_date' => now()->addDays(5)]);
        Sanctum::actingAs($user);

        $this->wallet($user, 500);
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'wallet', 'plan' => 'year'])->assertStatus(422);
        $this->assertEquals(500, WalletTransaction::balanceOf($user->id));

        $this->wallet($user, 200);
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'wallet', 'plan' => 'year'])->assertSuccessful();
        $this->assertEquals(100, WalletTransaction::balanceOf($user->id));
        $this->assertSame(Membership::PLAN_FULL, $card->fresh()->plan);
        $this->assertTrue($card->fresh()->expiration_date->isAfter(now()->addMonths(11)));
    }

    /** @test */
    public function the_membership_switch_and_the_half_year_plan_keep_the_wallet_out(): void
    {
        SiteSettings::put(MembershipPlan::PRICE, 600, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::MONTHS, 12, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::HALF_PRICE, 350, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::HALF_MONTHS, 6, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $user = User::factory()->create();
        Membership::factory()->create(['user_id' => $user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_TRIAL, 'expiration_date' => now()->addDays(5)]);
        Sanctum::actingAs($user);
        $this->wallet($user, 1000);

        $this->postJson('/api/v1/membership/subscribe', ['method' => 'wallet', 'plan' => 'half'])->assertStatus(422);

        SiteSettings::put(WalletPayments::MEMBERSHIP_ENABLED, '0', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
        $this->postJson('/api/v1/membership/subscribe', ['method' => 'wallet', 'plan' => 'year'])->assertStatus(422);
        $this->assertEquals(1000, WalletTransaction::balanceOf($user->id));
    }
}
