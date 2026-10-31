<?php

namespace Tests\Feature;

use App\Models\OrderPaymentLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\Paymob\PaymobHmac;
use App\Support\SiteSettings;
use App\Support\WalletPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Adding money to the wallet from the app: Paymob checkout, credited once by a signed callback of the right amount. */
class WalletTopupTest extends TestCase
{
    use RefreshDatabase;

    private const HMAC = 'test-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paymob.secret_key' => 'egy_sk_test_secret_value', 'services.paymob.public_key' => 'egy_pk_test_public',
            'services.paymob.hmac' => self::HMAC, 'services.paymob.integration_ids' => ['111'], 'services.paymob.currency' => 'EGP',
            'services.paymob.base_url' => 'https://accept.paymob.com',
        ]);
        Http::fake(['*/v1/intention/' => Http::response(['id' => 'int_1', 'client_secret' => 'egy_cs_abc'], 201)]);
    }

    private function signedPost(WalletTopup $topup, array $over = [], ?string $hmac = null): \Illuminate\Testing\TestResponse
    {
        $obj = array_replace_recursive([
            'id' => 7001, 'pending' => false, 'amount_cents' => (int) round($topup->amount * 100), 'success' => true,
            'is_auth' => false, 'is_capture' => false, 'is_standalone_payment' => true, 'is_voided' => false, 'is_refunded' => false, 'is_3d_secure' => true,
            'integration_id' => 111, 'has_parent_transaction' => false, 'currency' => 'EGP', 'created_at' => '2026-10-31T10:00:00.000000',
            'error_occured' => false, 'owner' => 77, 'order' => ['id' => 55, 'merchant_order_id' => $topup->paymob_reference],
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'], 'data' => ['txn_response_code' => 'APPROVED', 'message' => 'Approved'],
        ], $over);

        return $this->postJson('/api/v1/paymob/callback?hmac='.($hmac ?? PaymobHmac::compute($obj, self::HMAC)), ['type' => 'TRANSACTION', 'obj' => $obj]);
    }

    /** @test */
    public function it_opens_a_checkout_and_credits_nothing_until_a_signed_callback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/wallet/topup', ['amount' => 150])->assertOk();
        $this->assertNotEmpty($res->json('data.checkout_url'));
        $this->assertSame(0.0, WalletTransaction::balanceOf($user->id));
        $topup = WalletTopup::query()->firstOrFail();
        $this->assertSame('TOP'.$topup->id.'A1', $topup->paymob_reference);
        $this->assertSame('pending', $topup->status);
    }

    /** @test */
    public function a_signed_callback_for_the_right_amount_credits_the_wallet_once(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/topup', ['amount' => 150])->assertOk();
        $topup = WalletTopup::query()->firstOrFail();

        $this->signedPost($topup)->assertOk();
        $this->signedPost($topup)->assertOk();

        $this->assertEquals(150, WalletTransaction::balanceOf($user->id));
        $this->assertSame('paid', $topup->fresh()->status);
        $this->assertSame(1, WalletTransaction::query()->where('source', 'topup')->count());
        $this->assertTrue(OrderPaymentLog::query()->where('wallet_topup_id', $topup->id)->exists());
    }

    /** @test */
    public function a_forged_callback_a_wrong_amount_or_a_declined_payment_credit_nothing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/topup', ['amount' => 150])->assertOk();
        $topup = WalletTopup::query()->firstOrFail();

        $this->signedPost($topup, [], 'forged')->assertForbidden();
        $this->signedPost($topup, ['amount_cents' => 100])->assertOk();
        $this->assertEquals(0, WalletTransaction::balanceOf($user->id));

        $this->signedPost($topup, ['success' => false, 'data' => ['txn_response_code' => 'DECLINED', 'message' => 'Declined']])->assertOk();
        $this->assertSame('rejected', $topup->fresh()->status);
        $this->assertEquals(0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function the_amount_must_respect_the_admin_minimum_and_the_ceiling(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/topup', ['amount' => 10])->assertStatus(422);
        SiteSettings::put(WalletPayments::MIN_TOPUP, '5', Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $this->postJson('/api/v1/wallet/topup', ['amount' => 10])->assertOk();
        $this->postJson('/api/v1/wallet/topup', ['amount' => 20001])->assertStatus(422);
        $this->assertEquals(5, $this->getJson('/api/v1/loyalty')->json('data.wallet_min_topup'));
    }

    /** @test */
    public function it_needs_a_token(): void
    {
        $this->postJson('/api/v1/wallet/topup', ['amount' => 100])->assertUnauthorized();
    }
}
