<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\PaymentMethods;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin switches at /admin/setting decide which ways of paying every client shows. */
class PaymentMethodVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paymob.secret_key' => 'egy_sk_test',
            'services.paymob.public_key' => 'egy_pk_test',
            'services.paymob.integration_ids' => ['1', '2'],
            'services.paymob.card_integration_ids' => ['1'],
            'services.paymob.wallet_integration_ids' => ['2'],
        ]);
        SiteSettings::put(ShopDelivery::WALLET, '01000000000');
    }

    private function set(string $slug, bool $on): void
    {
        SiteSettings::put($slug, $on, Setting::TYPE_BOOLEAN);
    }

    /** @test */
    public function a_fresh_install_shows_paymob_only(): void
    {
        $this->assertSame(['paymob'], PaymentMethods::orderTypes());
    }

    /** @test */
    public function cod_appears_when_the_admin_opens_it_and_goes_when_hidden(): void
    {
        $this->set(PaymentMethods::COD, true);
        $this->assertContains('cod', PaymentMethods::orderTypes());
        $this->assertTrue(PaymentMethods::flags()['cod']);

        $this->set(PaymentMethods::COD, false);
        $this->assertNotContains('cod', PaymentMethods::orderTypes());
    }

    /** @test */
    public function the_shop_endpoint_tells_clients_what_to_draw(): void
    {
        $this->set(PaymentMethods::COD, true);

        $this->getJson('/api/v1/mobile/shop')->assertOk()
            ->assertJsonPath('data.payment_methods', ['online' => true, 'online_card' => true, 'online_wallet' => true, 'cod' => true, 'wallet' => false, 'instapay' => false]);
    }

    /** @test */
    public function a_hidden_method_is_refused_when_an_order_is_placed(): void
    {
        config(['services.partner_api.key' => 'partner-test-key']);

        $this->withHeader('X-Api-Key', 'partner-test-key')->postJson('/api/v1/partner/orders', ['payment_type' => 'cod'])
            ->assertJsonValidationErrors('payment_type');
    }

    /** @test */
    public function paymob_can_be_hidden_even_with_its_keys_set(): void
    {
        $this->set(PaymentMethods::ONLINE_CARD, false);
        $this->set(PaymentMethods::ONLINE_WALLET, false);

        $this->assertFalse(PaymentMethods::online());
        $this->assertSame([], PaymentMethods::orderTypes());
    }

    /** @test */
    public function card_and_wallet_are_separate_switches(): void
    {
        $this->set(PaymentMethods::ONLINE_WALLET, false);
        $this->assertSame(['card'], PaymentMethods::onlineChannels());
        $this->assertFalse(PaymentMethods::channelAllowed('wallet'));

        $this->set(PaymentMethods::ONLINE_CARD, false);
        $this->set(PaymentMethods::ONLINE_WALLET, true);
        $this->assertSame(['wallet'], PaymentMethods::onlineChannels());
        $this->assertFalse(PaymentMethods::channelAllowed('card'));
    }

    /** @test */
    public function the_wallet_button_needs_a_wallet_integration_id(): void
    {
        config(['services.paymob.wallet_integration_ids' => []]);

        $this->assertSame(['card'], PaymentMethods::onlineChannels());
    }

    /** @test */
    public function the_chosen_channel_decides_which_integration_paymob_is_asked_for(): void
    {
        $call = function (?string $channel): array {
            $request = \Illuminate\Http\Request::create('/x', 'POST', $channel ? ['paymob_channel' => $channel] : []);
            $client = new \App\Services\Paymob\PaymobClient(null, $request);
            $method = new \ReflectionMethod($client, 'paymentMethods');

            return $method->invoke($client);
        };

        $this->assertSame([1], $call('card'));
        $this->assertSame([2], $call('wallet'));
        $this->assertSame([1, 2], $call(null));
    }
}
