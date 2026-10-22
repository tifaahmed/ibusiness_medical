<?php

namespace Tests\Feature;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\User\UserRoleEnum;
use App\Mail\OrderReceiptMail;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderPaymentLog;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Paymob\PaymobHmac;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Paymob online payment, the cash-on-delivery fee, receipts, and the payment log.
 *
 * Everything runs behind Http::fake() — nothing here may reach Paymob. Run on a
 * throwaway database (see AGENTS.md); these tests only roll back, never commit.
 */
class PaymobPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const HMAC = 'test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->showAllPaymentMethods();

        config([
            'services.partner_api.key' => 'partner-test-key',
            'services.paymob.secret_key' => 'egy_sk_test_secret_value',
            'services.paymob.public_key' => 'egy_pk_test_public',
            'services.paymob.api_key' => 'legacy-api-key',
            'services.paymob.hmac' => self::HMAC,
            'services.paymob.integration_ids' => ['111', '222'],
            'services.paymob.currency' => 'EGP',
            'services.paymob.base_url' => 'https://accept.paymob.com',
            'services.paymob.return_url' => 'https://shop.test/orders/{code}',
        ]);

        SiteSettings::put(ShopDelivery::COST, 20, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::PRICE, 50, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::THRESHOLD, '', Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::WALLET, '01000000000');
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => ['en' => 'Coffee', 'ar' => 'قهوة'],
            'slug' => 'coffee-'.Str::lower(Str::random(6)),
            'old_price' => 100, 'new_price' => 100,
            'is_purchasable' => true, 'is_visible' => true,
        ]);
    }

    private function place(string $paymentType, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', array_merge([
            'customer_full_name' => 'Ahmed Ali',
            'customer_phone' => '01012345678',
            'customer_address' => 'Cairo, street 1',
            'payment_type' => $paymentType,
            'items' => [['slug' => $this->product()->slug, 'quantity' => 2]],
        ], $extra));
    }

    private function fakeIntention(): void
    {
        Http::fake([
            '*/v1/intention/' => Http::response(['id' => 'int_1', 'client_secret' => 'egy_cs_abc', 'intention_order_id' => 55], 201),
        ]);
    }

    /** A signed callback body for the order, as Paymob would post it. */
    private function signedCallback(Order $order, array $overrides = []): array
    {
        $obj = array_replace_recursive([
            'id' => 9001,
            'pending' => false,
            'amount_cents' => (int) round($order->total_amount * 100),
            'success' => true,
            'is_auth' => false, 'is_capture' => false, 'is_standalone_payment' => true,
            'is_voided' => false, 'is_refunded' => false, 'is_3d_secure' => true,
            'integration_id' => 111, 'has_parent_transaction' => false,
            'currency' => 'EGP', 'created_at' => '2026-10-18T10:00:00.000000',
            'error_occured' => false, 'owner' => 77,
            'order' => ['id' => 55, 'merchant_order_id' => $order->paymob_reference ?? $order->order_code],
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'data' => ['txn_response_code' => 'APPROVED', 'message' => 'Approved'],
        ], $overrides);

        return ['type' => 'TRANSACTION', 'obj' => $obj, 'hmac' => PaymobHmac::compute($obj, self::HMAC)];
    }

    private function postCallback(array $payload, ?string $hmac = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/paymob/callback?hmac='.($hmac ?? $payload['hmac']), ['type' => $payload['type'], 'obj' => $payload['obj']]);
    }

    private function onlineOrder(array $extra = []): Order
    {
        $this->fakeIntention();
        $this->place('paymob', $extra)->assertCreated();

        return Order::query()->latest('id')->firstOrFail();
    }

    // ---- COD fee -------------------------------------------------------

    public function test_cod_fee_is_added_to_a_cod_order_only(): void
    {
        SiteSettings::put(ShopDelivery::COD_FEE, 15, Setting::TYPE_NUMBER);

        $this->place('cod')->assertCreated()->assertJsonPath('order.cod_fee', 15);
        $cod = Order::query()->latest('id')->first();
        $this->assertSame('265.00', $cod->total_amount); // 200 + 50 delivery + 15 fee
        $this->assertSame('265.00', $cod->total_amount_before_discount);

        $wallet = $this->place('transfer-wallet')->assertCreated();
        $this->assertSame('250.00', Order::query()->latest('id')->first()->total_amount);
        $wallet->assertJsonPath('order.cod_fee', 0);
    }

    public function test_zero_cod_fee_changes_nothing(): void
    {
        $this->place('cod')->assertCreated()->assertJsonPath('order.cod_fee', 0);
        $this->assertSame('250.00', Order::query()->latest('id')->first()->total_amount);
    }

    public function test_the_fee_cannot_be_set_by_the_caller(): void
    {
        $this->place('cod', ['cod_fee' => 999])->assertCreated();
        $this->assertSame('250.00', Order::query()->latest('id')->first()->total_amount);
    }

    // ---- Starting a payment --------------------------------------------

    public function test_online_order_returns_a_checkout_url_and_logs_the_exchange_verbatim(): void
    {
        $this->fakeIntention();

        $response = $this->place('paymob')->assertCreated();

        $this->assertStringContainsString('clientSecret=egy_cs_abc', $response->json('payment.checkout_url'));
        $this->assertStringContainsString('publicKey=egy_pk_test_public', $response->json('payment.checkout_url'));
        // The embedded card form (Paymob Pixel) mounts with exactly these two.
        $this->assertSame('egy_cs_abc', $response->json('payment.client_secret'));
        $this->assertSame('egy_pk_test_public', $response->json('payment.public_key'));

        $order = Order::query()->latest('id')->first();
        $this->assertSame(PaymentStatusEnum::PENDING, $order->payment_status);

        $log = OrderPaymentLog::query()->where('event', OrderPaymentLog::EVENT_INTENTION)->firstOrFail();
        $this->assertSame('out', $log->direction);
        $this->assertSame(201, $log->http_status);
        $this->assertNotNull($log->duration_ms);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        $this->assertStringContainsString('"amount":25000', $log->request_body);
        $this->assertStringContainsString('"special_reference":"'.$order->order_code.'"', $log->request_body);
        $this->assertStringContainsString('egy_cs_abc', $log->response_body); // verbatim
        // Credentials are the one thing masked.
        $this->assertStringNotContainsString('egy_sk_test_secret_value', json_encode($log->request_headers));

        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'action' => OrderLog::ACTION_PAYMENT_INITIATED]);
    }

    public function test_every_payment_tells_paymob_where_it_came_from(): void
    {
        $this->fakeIntention();
        $this->place('paymob', ['source' => 'mobile_app'])->assertCreated();
        $order = Order::query()->latest('id')->firstOrFail();

        Http::assertSent(function ($request) use ($order) {
            $extras = $request->data()['extras'] ?? [];

            return str_ends_with($request->url(), '/v1/intention/')
                && ($extras['app'] ?? null) === 'Deilar'
                && ($extras['kind'] ?? null) === 'order'
                && ($extras['order_code'] ?? null) === $order->order_code
                && ($extras['channel'] ?? null) === $order->source
                && ! empty($extras['env'])
                && ! array_key_exists('customer_phone', $extras)
                && ! str_contains(json_encode($extras), 'Ahmed');
        });
    }

    public function test_a_buyer_with_no_email_still_sends_paymob_a_valid_one(): void
    {
        $this->fakeIntention();
        $this->place('paymob')->assertCreated();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_ends_with($request->url(), '/v1/intention/')
                && filter_var($body['billing_data']['email'] ?? '', FILTER_VALIDATE_EMAIL) !== false
                && filter_var($body['customer']['email'] ?? '', FILTER_VALIDATE_EMAIL) !== false;
        });
    }

    public function test_items_add_up_to_the_amount_sent_to_paymob(): void
    {
        $this->fakeIntention();
        $this->place('paymob')->assertCreated();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_ends_with($request->url(), '/v1/intention/')
                && array_sum(array_column($body['items'], 'amount')) === $body['amount']
                && $body['payment_methods'] === [111, 222];
        });
    }

    public function test_gateway_outage_keeps_the_order_and_says_so(): void
    {
        Http::fake(['*/v1/intention/' => Http::response(['detail' => 'bad key'], 401)]);

        $response = $this->place('paymob')->assertCreated();

        $response->assertJsonPath('payment.checkout_url', null);
        $this->assertSame(1, Order::query()->count());
        $log = OrderPaymentLog::query()->firstOrFail();
        $this->assertFalse($log->success);
        $this->assertSame(401, $log->http_status);
        $this->assertStringContainsString('bad key', $log->response_body);
    }

    public function test_online_payment_is_refused_when_paymob_is_not_configured(): void
    {
        config(['services.paymob.secret_key' => null]);

        $this->place('paymob')->assertUnprocessable()->assertJsonValidationErrors('payment_type');
    }

    public function test_a_retry_uses_a_fresh_reference(): void
    {
        $order = $this->onlineOrder();
        $this->fakeIntention();

        $this->withHeader('X-Api-Key', 'partner-test-key')
            ->postJson("/api/v1/partner/orders/{$order->order_code}/pay")->assertOk();

        $this->assertSame($order->order_code.'-2', $order->fresh()->paymob_reference);
    }

    // ---- Callback ------------------------------------------------------

    public function test_signed_success_callback_accepts_the_payment_and_mails_the_receipt_once(): void
    {
        Mail::fake();
        $order = $this->onlineOrder(['customer_email' => 'buyer@example.com']);
        $payload = $this->signedCallback($order);

        $this->postCallback($payload)->assertOk()->assertJsonPath('ok', true);
        $this->postCallback($payload)->assertOk(); // Paymob retries; must be harmless

        $order->refresh();
        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->payment_status);
        $this->assertSame(OrderStatusEnum::SUCCESS, $order->order_status);
        $this->assertSame('250.00', $order->total_paid);
        $this->assertSame('9001', $order->paymob_transaction_id);
        $this->assertSame('APPROVED', $order->paymob_response_code);

        Mail::assertSent(OrderReceiptMail::class, 1);
        Mail::assertSent(OrderReceiptMail::class, fn ($mail) => $mail->hasTo('buyer@example.com'));

        $inbound = OrderPaymentLog::query()->where('event', OrderPaymentLog::EVENT_CALLBACK)->first();
        $this->assertSame('in', $inbound->direction);
        $this->assertSame(200, $inbound->http_status);
        $this->assertSame('APPROVED', $inbound->gateway_code);
        $this->assertStringContainsString('"merchant_order_id"', $inbound->request_body);
        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'action' => OrderLog::ACTION_PAYMENT_ACCEPTED]);
        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'action' => OrderLog::ACTION_PAYMENT_STATUS_CHANGED]);
    }

    public function test_unsigned_or_forged_callback_changes_nothing(): void
    {
        $order = $this->onlineOrder();
        $payload = $this->signedCallback($order);

        $this->postCallback($payload, 'deadbeef')->assertForbidden();

        $tampered = $payload;
        $tampered['obj']['success'] = false;
        $this->postCallback($tampered, $payload['hmac'])->assertForbidden();

        $this->assertSame(PaymentStatusEnum::PENDING, $order->fresh()->payment_status);
        // The refused hits are on the record too.
        $this->assertSame(2, OrderPaymentLog::query()->where('event', OrderPaymentLog::EVENT_CALLBACK)->where('http_status', 403)->count());
    }

    public function test_a_paid_flag_for_the_wrong_amount_is_not_accepted(): void
    {
        $order = $this->onlineOrder();

        $this->postCallback($this->signedCallback($order, ['amount_cents' => 100]))->assertOk();

        $this->assertSame(PaymentStatusEnum::PENDING, $order->fresh()->payment_status);
    }

    public function test_declined_payment_is_rejected_and_a_later_success_still_wins(): void
    {
        $order = $this->onlineOrder();

        $this->postCallback($this->signedCallback($order, [
            'success' => false, 'data' => ['txn_response_code' => 'DECLINED', 'message' => 'Do not honour'],
        ]))->assertOk();

        $order->refresh();
        $this->assertSame(PaymentStatusEnum::REJECTED, $order->payment_status);
        $this->assertSame(OrderStatusEnum::FAILED, $order->order_status);
        $this->assertSame('DECLINED', $order->paymob_response_code);
        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'action' => OrderLog::ACTION_PAYMENT_REJECTED]);

        $this->postCallback($this->signedCallback($order))->assertOk();
        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
    }

    public function test_a_late_failure_never_unpays_a_paid_order(): void
    {
        $order = $this->onlineOrder();
        $this->postCallback($this->signedCallback($order))->assertOk();

        $this->postCallback($this->signedCallback($order, ['id' => 9002, 'success' => false]))->assertOk();

        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
    }

    public function test_no_email_means_no_receipt_and_no_error(): void
    {
        Mail::fake();
        $order = $this->onlineOrder();

        $this->postCallback($this->signedCallback($order))->assertOk();

        Mail::assertNothingSent();
        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
    }

    public function test_cod_order_gets_its_receipt_at_once_when_there_is_an_email(): void
    {
        Mail::fake();

        $this->place('cod', ['customer_email' => 'cod@example.com'])->assertCreated();

        Mail::assertSent(OrderReceiptMail::class, fn ($mail) => $mail->hasTo('cod@example.com'));
    }

    public function test_browser_return_redirects_to_the_storefront_and_applies_a_signed_result(): void
    {
        $order = $this->onlineOrder();
        $payload = $this->signedCallback($order);
        // The browser carries the same fields flat, dots turned to underscores by PHP.
        $flat = [
            'id' => 9001, 'success' => 'true', 'pending' => 'false', 'amount_cents' => $payload['obj']['amount_cents'],
            'currency' => 'EGP', 'created_at' => '2026-10-18T10:00:00.000000', 'error_occured' => 'false',
            'has_parent_transaction' => 'false', 'integration_id' => 111, 'is_3d_secure' => 'true', 'is_auth' => 'false',
            'is_capture' => 'false', 'is_refunded' => 'false', 'is_standalone_payment' => 'true', 'is_voided' => 'false',
            'order' => 55, 'owner' => 77, 'source_data_pan' => '2346', 'source_data_sub_type' => 'MasterCard', 'source_data_type' => 'card',
            'merchant_order_id' => $order->order_code,
        ];
        $flat['hmac'] = $payload['hmac'];

        $this->get(route('api.v1.paymob.return', ['code' => $order->order_code]).'?'.http_build_query($flat))
            ->assertRedirect("https://shop.test/orders/{$order->order_code}?order={$order->order_code}&status=paid");

        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
        $this->assertTrue(OrderPaymentLog::query()->where('event', OrderPaymentLog::EVENT_RETURN)->exists());
    }

    public function test_unsigned_browser_return_shows_pending_and_changes_nothing(): void
    {
        $order = $this->onlineOrder();

        $this->get(route('api.v1.paymob.return', ['code' => $order->order_code]).'?success=true&id=1')
            ->assertRedirect("https://shop.test/orders/{$order->order_code}?order={$order->order_code}&status=pending");

        $this->assertSame(PaymentStatusEnum::PENDING, $order->fresh()->payment_status);
    }

    // ---- Admin buttons -------------------------------------------------

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-pay', 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage orders', 'web'));
        $user->assignRole($role);

        return $user;
    }

    public function test_check_button_asks_paymob_and_repairs_a_missed_callback(): void
    {
        $order = $this->onlineOrder();
        $callback = $this->signedCallback($order)['obj'];
        Http::fake([
            '*/api/auth/tokens' => Http::response(['token' => 'secret-jwt-token']),
            '*/transaction_inquiry' => Http::response($callback),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.order.payment.check', $order->order_code))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
        $inquiry = OrderPaymentLog::query()->where('event', OrderPaymentLog::EVENT_INQUIRY)->firstOrFail();
        $this->assertSame($admin->id, $inquiry->admin_id);
        $this->assertSame('APPROVED', $inquiry->gateway_code);
        // The short-lived token is never written down, in either direction.
        $this->assertStringNotContainsString('secret-jwt-token', json_encode(OrderPaymentLog::all()->toArray()));
        $this->assertStringNotContainsString('legacy-api-key', json_encode(OrderPaymentLog::all()->toArray()));
        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'admin_id' => $admin->id, 'action' => OrderLog::ACTION_PAYMENT_ACCEPTED]);
    }

    public function test_full_refund_marks_the_order_refunded_and_a_repeat_callback_does_not_double_count(): void
    {
        $order = $this->onlineOrder();
        $this->postCallback($this->signedCallback($order))->assertOk();

        $refundTxn = [
            'id' => 9100, 'success' => true, 'pending' => false, 'is_refunded' => true, 'is_voided' => false,
            'amount_cents' => 25000, 'currency' => 'EGP', 'parent_transaction' => 9001,
            'order' => ['id' => 55, 'merchant_order_id' => $order->order_code],
            'data' => ['txn_response_code' => 'APPROVED'],
        ];
        Http::fake(['*/void_refund/refund' => Http::response($refundTxn)]);
        $admin = $this->admin();

        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.order.payment.refund', $order->order_code), ['amount' => 250, 'reason' => 'customer changed mind'])
            ->assertRedirect()->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(PaymentStatusEnum::REFUNDED, $order->payment_status);
        $this->assertSame('250.00', $order->refunded_amount);
        $log = OrderLog::query()->where('action', OrderLog::ACTION_PAYMENT_REFUNDED)->firstOrFail();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame('customer changed mind', $log->new_values['reason']);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/void_refund/refund')
            && $r->data() === ['transaction_id' => 9001, 'amount_cents' => 25000]);

        // Paymob later calls back about the very same refund.
        $callbackRefund = $this->signedCallback($order, array_merge($refundTxn, ['has_parent_transaction' => true]));
        $this->postCallback($callbackRefund)->assertOk();
        $this->assertSame('250.00', $order->fresh()->refunded_amount);
    }

    public function test_refund_cannot_exceed_what_was_paid(): void
    {
        $order = $this->onlineOrder();
        $this->postCallback($this->signedCallback($order))->assertOk();
        Http::fake();

        $this->actingAs($this->admin())->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.order.payment.refund', $order->order_code), ['amount' => 9999])
            ->assertSessionHasErrors('amount');

        Http::assertNothingSent();
    }

    public function test_a_refused_refund_is_logged_and_leaves_the_order_paid(): void
    {
        $order = $this->onlineOrder();
        $this->postCallback($this->signedCallback($order))->assertOk();
        Http::fake(['*/void_refund/refund' => Http::response(['detail' => 'insufficient balance'], 400)]);

        $this->actingAs($this->admin())->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.order.payment.refund', $order->order_code), ['amount' => 50])
            ->assertSessionHas('error');

        $this->assertSame(PaymentStatusEnum::ACCEPTED, $order->fresh()->payment_status);
        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'action' => OrderLog::ACTION_REFUND_FAILED]);
        $this->assertTrue(OrderPaymentLog::query()->where('event', OrderPaymentLog::EVENT_REFUND)->where('success', false)->exists());
    }

    public function test_order_page_carries_the_payment_tab_data(): void
    {
        $order = $this->onlineOrder();
        $this->postCallback($this->signedCallback($order))->assertOk();

        $this->actingAs($this->admin())
            ->get(route('admin.order.show', $order->order_code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('order.paymob.can_refund', true)
                ->where('order.paymob.transaction_id', '9001')
                ->has('order.payment_logs', 3));
    }
}
