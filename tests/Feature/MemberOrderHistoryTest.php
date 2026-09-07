<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A signed-in member's order history.
 *
 * The storefront still has no accounts in the sense the shop was built with —
 * the order code opens an order for a guest, and always will. What signing in
 * adds is an owner: orders placed while signed in carry one from the start, and
 * `claim` hands a member the guest orders their own browser remembers.
 */
class MemberOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_member_sees_only_their_own_orders(): void
    {
        $member = $this->member();
        $stranger = $this->member('01055555555');

        $mine = Order::query()->create($this->orderAttributes(['user_id' => $member->id]));
        Order::query()->create($this->orderAttributes(['user_id' => $stranger->id]));
        Order::query()->create($this->orderAttributes(['user_id' => null]));

        Sanctum::actingAs($member);

        $response = $this->getJson('/api/v1/orders')->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($mine->order_code, $response->json('data.0.order_code'));
    }

    /** @test */
    public function the_history_is_not_readable_without_a_token(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->postJson('/api/v1/orders/claim', ['codes' => ['ABCD1234']])->assertUnauthorized();
    }

    /** @test */
    public function signing_in_claims_the_guest_orders_this_browser_placed(): void
    {
        $member = $this->member();

        $guest = Order::query()->create($this->orderAttributes(['user_id' => null]));

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/orders/claim', ['codes' => [$guest->order_code]])
            ->assertOk()
            ->assertJsonPath('data.claimed', [$guest->order_code])
            ->assertJsonPath('data.total', 1);

        $this->assertSame($member->id, $guest->refresh()->user_id);
    }

    /** @test */
    public function an_order_that_already_belongs_to_somebody_is_never_re_pointed(): void
    {
        /*
         * The order code travels: it is pasted into chats and read off
         * receipts. Once an order has an owner, knowing the code must not be
         * enough to take it from them.
         */
        $owner = $this->member();
        $opportunist = $this->member('01055555555');

        $order = Order::query()->create($this->orderAttributes(['user_id' => $owner->id]));

        Sanctum::actingAs($opportunist);

        $this->postJson('/api/v1/orders/claim', ['codes' => [$order->order_code]])
            ->assertOk()
            ->assertJsonPath('data.claimed', []);

        $this->assertSame($owner->id, $order->refresh()->user_id);
    }

    /** @test */
    public function a_code_that_matches_nothing_is_simply_not_claimed(): void
    {
        $member = $this->member();

        Sanctum::actingAs($member);

        $this->postJson('/api/v1/orders/claim', ['codes' => ['NOSUCH11']])
            ->assertOk()
            ->assertJsonPath('data.claimed', [])
            ->assertJsonPath('data.total', 0);
    }

    /** @test */
    public function an_order_placed_while_signed_in_belongs_to_the_member_from_the_start(): void
    {
        config(['services.partner_api.key' => 'partner-test-key']);

        $member = $this->member();
        $token = $member->createToken('storefront-phone-login')->plainTextToken;

        $product = $this->product();

        $this->withHeaders([
            'X-Api-Key' => 'partner-test-key',
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/partner/orders', [
            'items' => [['slug' => $product->slug, 'quantity' => 1]],
            'customer_full_name' => 'Test Buyer',
            'customer_phone' => '01062587475',
            'customer_address' => 'Somewhere',
            'payment_type' => 'cod',
        ])->assertCreated();

        $this->assertSame($member->id, Order::query()->latest('id')->first()->user_id);
    }

    /** @test */
    public function a_guest_checkout_still_belongs_to_nobody(): void
    {
        config(['services.partner_api.key' => 'partner-test-key']);

        $product = $this->product();

        $this->withHeader('X-Api-Key', 'partner-test-key')
            ->postJson('/api/v1/partner/orders', [
                'items' => [['slug' => $product->slug, 'quantity' => 1]],
                'customer_full_name' => 'Test Buyer',
                'customer_phone' => '01062587475',
                'customer_address' => 'Somewhere',
                'payment_type' => 'cod',
            ])->assertCreated();

        $this->assertNull(Order::query()->latest('id')->first()->user_id);
    }

    /**
     * A purchasable product to order. Built by hand rather than by a factory:
     * `Product` has none, and the order endpoint only needs a slug it can price.
     */
    private function product(): \App\Models\Product
    {
        return \App\Models\Product::query()->create([
            'name' => ['en' => 'Test product', 'ar' => 'منتج'],
            'slug' => 'test-product-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
            'old_price' => 100,
            'new_price' => 80,
            'is_purchasable' => true,
            'is_visible' => true,
        ]);
    }

    private function member(string $phone = '01062587475'): User
    {
        $user = User::factory()->create(['phone' => $phone]);

        Membership::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'is_visible' => true,
        ]);

        return $user->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function orderAttributes(array $overrides = []): array
    {
        return [
            'order_code' => Order::generateCode(),
            'total_amount' => 100,
            'total_amount_before_discount' => 100,
            'total_paid' => 0,
            'customer_full_name' => 'Test Buyer',
            'customer_phone' => '01062587475',
            'customer_address' => 'Somewhere',
            'payment_type' => 'cod',
            'payment_status' => 'pending',
            'delivery_status' => 'pending',
            'source' => Order::SOURCE_STOREFRONT,
            ...$overrides,
        ];
    }
}
