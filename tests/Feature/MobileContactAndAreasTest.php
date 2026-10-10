<?php

namespace Tests\Feature;

use App\Enums\Contact\ContactSourceEnum;
use App\Models\ContactMessage;
use App\Models\Address;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Support\ShopContact;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the Deilar mobile app posts to the Contact Us inbox, and the area
 * picker's nearest-borders query.
 */
class MobileContactAndAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_new_visitor_popup_lands_in_the_inbox_with_its_name(): void
    {
        $this->postJson('/api/contact-messages', [
            'phone' => '01020709993',
            'name' => 'Mona Ali',
            'message' => 'New visitor: Mona Ali',
            'source' => ContactSourceEnum::CARD_POPUP->value,
        ])->assertSuccessful();

        $enquiry = ContactMessage::query()->firstOrFail();

        $this->assertSame('Mona Ali', $enquiry->name);
        $this->assertSame('01020709993', $enquiry->phone);
        $this->assertSame(ContactSourceEnum::CARD_POPUP, $enquiry->source);
    }

    public function test_the_name_is_optional(): void
    {
        $this->postJson('/api/contact-messages', ['phone' => '01020709993', 'message' => 'Hello'])->assertSuccessful();

        $this->assertNull(ContactMessage::query()->firstOrFail()->name);
    }

    public function test_nearest_borders_accepts_a_governorate_and_drops_geometry(): void
    {
        $this->getJson('/api/v1/locations/nearest-borders?level=city&lat=30&lng=31&limit=60&governorate_id=1&geometry=0')
            ->assertSuccessful()
            ->assertJsonStructure(['borders']);
    }

    public function test_the_app_quotes_the_delivery_set_in_the_admin_settings(): void
    {
        SiteSettings::put(ShopDelivery::PRICE, 60, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::THRESHOLD, 900, Setting::TYPE_NUMBER);

        $this->getJson('/api/v1/mobile/shop')
            ->assertSuccessful()
            ->assertJsonPath('data.delivery_price', 60)
            ->assertJsonPath('data.free_delivery_threshold', 900);
    }

    public function test_an_empty_threshold_means_delivery_is_never_free(): void
    {
        SiteSettings::put(ShopDelivery::THRESHOLD, '', Setting::TYPE_NUMBER);

        $this->getJson('/api/v1/mobile/shop')
            ->assertSuccessful()
            ->assertJsonPath('data.free_delivery_threshold', null);
    }

    public function test_before_anything_is_edited_the_install_defaults_apply(): void
    {
        $this->getJson('/api/v1/mobile/shop')
            ->assertSuccessful()
            ->assertJsonPath('data.delivery_price', (int) config('mobile_orders.delivery_price'));
    }

    public function test_the_shop_settings_feed_for_the_website_is_key_gated_and_carries_the_wallet(): void
    {
        config(['services.partner_api.key' => 'test-key']);
        SiteSettings::put(ShopDelivery::WALLET, '01055554444', Setting::TYPE_PHONE);
        SiteSettings::put(ShopDelivery::PRICE, 40, Setting::TYPE_NUMBER);
        SiteSettings::put(ShopDelivery::COST, 25, Setting::TYPE_NUMBER);

        $this->getJson('/api/v1/partner/shop-settings')->assertUnauthorized();

        $this->getJson('/api/v1/partner/shop-settings', ['X-Api-Key' => 'test-key'])
            ->assertSuccessful()
            ->assertJsonPath('walletNumber', '01055554444')
            ->assertJsonPath('deliveryPrice', 40)
            ->assertJsonPath('deliveryCost', 25)
            ->assertJsonPath('deliveryProfit', 15);
    }

    public function test_the_contact_points_come_from_the_admin_settings(): void
    {
        config(['services.partner_api.key' => 'test-key']);
        SiteSettings::put(ShopContact::PHONE, '01011112222', Setting::TYPE_PHONE);
        SiteSettings::put(ShopContact::FACEBOOK, '', Setting::TYPE_URL);

        $this->getJson('/api/v1/mobile/shop')
            ->assertSuccessful()
            ->assertJsonPath('data.contact.phone', '01011112222')
            ->assertJsonPath('data.contact.facebook_url', null);

        $this->getJson('/api/v1/partner/contact-settings')->assertUnauthorized();
        $this->getJson('/api/v1/partner/contact-settings', ['X-Api-Key' => 'test-key'])
            ->assertSuccessful()
            ->assertJsonPath('phone', '01011112222');
    }

    public function test_a_missing_wallet_number_is_flagged_for_the_admin_with_where_to_add_it(): void
    {
        SiteSettings::put(ShopDelivery::WALLET, '', Setting::TYPE_PHONE);

        $missing = ShopDelivery::missingSetup();

        $this->assertCount(1, $missing);
        $this->assertSame(ShopDelivery::WALLET, $missing[0]['slug']);
        $this->assertStringContainsString('/admin/setting/', $missing[0]['url']);

        SiteSettings::put(ShopDelivery::WALLET, '01055554444', Setting::TYPE_PHONE);

        $this->assertSame([], ShopDelivery::missingSetup());
    }

    public function test_the_signed_in_member_gets_the_saved_address_with_the_card_home_first(): void
    {
        $user = User::factory()->create();
        $membership = Membership::factory()->active()->create(['user_id' => $user->id]);
        Address::factory()->create(['membership_id' => $membership->id, 'type' => 'work', 'street' => 'Work St']);
        Address::factory()->create(['membership_id' => $membership->id, 'type' => 'home', 'street' => 'Home St', 'building_number' => '12']);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/me')
            ->assertSuccessful()
            ->assertJsonPath('address.type', 'home')
            ->assertJsonPath('address.street', 'Home St')
            ->assertJsonPath('address.building_number', '12');
    }

    public function test_a_member_with_no_saved_address_gets_null(): void
    {
        $user = User::factory()->create();
        Membership::factory()->active()->create(['user_id' => $user->id]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/me')->assertSuccessful()->assertJsonPath('address', null);
    }
}
