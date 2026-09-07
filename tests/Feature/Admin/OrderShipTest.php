<?php

namespace Tests\Feature\Admin;

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use App\Enums\User\UserRoleEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use App\Services\Abs\AbsAddressMatcher;
use App\Services\Abs\AbsShipmentPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Handing an order to ABS Courier.
 *
 * Every test here runs behind `Http::fake()`. Nothing in this file may ever
 * reach the real API: a leak would book an actual courier, against a real
 * customer's address, for a parcel that does not exist.
 */
class OrderShipTest extends TestCase
{
    use RefreshDatabase;

    private const GOVERNORATES = [
        ['id' => 1, 'value' => 'القاهرة'],
        ['id' => 2, 'value' => 'الجيزة'],
        ['id' => 3, 'value' => 'الإسكندرية'],
    ];

    private const CITIES = [
        ['id' => 11, 'value' => 'السادس من أكتوبر'],
        ['id' => 12, 'value' => 'الشيخ زايد'],
        ['id' => 13, 'value' => 'الدقي'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.abs.key' => 'test-key',
            'services.abs.base_url' => 'https://dev.core.absegy.com',
            'services.abs.location_id' => 7,
            'services.abs.sub_account' => null,
            'services.abs.product_id' => null,
        ]);
    }

    private function adminWith(string $permission): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-ship-'.str_replace(' ', '-', $permission), 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $user->assignRole($role);

        return $user;
    }

    private function seedOrder(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'order_code' => 'DL-SHIPTEST',
            'total_paid' => 0,
            'total_amount' => 450.00,
            'total_amount_before_discount' => 500.00,
            'customer_full_name' => 'أحمد محمود علي',
            'customer_phone' => '01070274943',
            'customer_address' => 'قطعة 560 بجوار المول',
            'customer_street' => 'شارع النصر',
            'customer_governorate' => 'الجيزة',
            'customer_city' => 'السادس من أكتوبر',
            'customer_building_number' => '560',
            'customer_apartment_number' => '1',
            'customer_floor_number' => '2',
            'payment_status' => PaymentStatusEnum::PENDING,
            'delivery_status' => DeliveryStatusEnum::PENDING,
            'payment_type' => PaymentTypeEnum::COD,
            'source' => Order::SOURCE_STOREFRONT,
        ], $overrides));

        $order->products()->create([
            'product_id' => null,
            'name' => ['ar' => 'جهاز قياس الضغط', 'en' => 'Blood pressure monitor'],
            'slug' => 'bp-monitor',
            'quantity' => 2,
            'old_price' => 250.00,
            'new_price' => 225.00,
            'line_total' => 450.00,
            'cost_price' => 150.00,
            'profit_price' => 75.00,
        ]);

        return $order->refresh();
    }

    /**
     * The ABS endpoints, answering as their documented schema says they do.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function fakeAbs(array $overrides = []): void
    {
        Http::fake(array_merge([
            '*/shared/dropdown/governorates*' => Http::response([
                'success' => true,
                'data' => ['content' => self::GOVERNORATES],
            ]),
            '*/shared/dropdown/cities*' => Http::response([
                'success' => true,
                'data' => ['content' => self::CITIES],
            ]),
            '*/create-shipment' => Http::response([
                'success' => true,
                'message' => 'Shipment created successfully',
                'data' => 'ABS-123456789',
            ], 201),
        ], $overrides));
    }

    // ---- Preview -------------------------------------------------------

    public function test_preview_auto_matches_the_stored_arabic_governorate_and_city(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->getJson(route('admin.order.ship.preview', $order->order_code));

        $response->assertOk()
            ->assertJsonPath('destination.governorate.selected_id', 2)
            ->assertJsonPath('destination.governorate.matched', true)
            ->assertJsonPath('destination.city.selected_id', 11)
            ->assertJsonPath('destination.city.matched', true)
            /* The payload is the whole point: it must be the real body, ready
               to post, not a summary of one. */
            ->assertJsonPath('payload.cmpService', 'DELIVERY')
            ->assertJsonPath('payload.locationId', 7)
            ->assertJsonPath('payload.shipment.ref', 'DL-SHIPTEST')
            ->assertJsonPath('payload.shipment.consigneePhone', '+201070274943');
    }

    public function test_preview_flags_an_unmatched_city_instead_of_guessing_one(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder(['customer_city' => 'مدينة لا توجد لديهم']);

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->getJson(route('admin.order.ship.preview', $order->order_code));

        $response->assertOk()
            ->assertJsonPath('destination.city.selected_id', null)
            ->assertJsonPath('destination.city.matched', false);

        $this->assertContains('no_city_match', $response->json('warnings'));
    }

    public function test_preview_never_books_anything(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->getJson(route('admin.order.ship.preview', $order->order_code))
            ->assertOk();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'create-shipment'));
        $this->assertNull($order->refresh()->abs_awb);
    }

    public function test_preview_refuses_an_order_that_already_shipped(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder(['abs_awb' => 'ABS-EXISTING', 'abs_shipped_at' => now()]);

        $this->actingAs($this->adminWith('manage orders'))
            ->getJson(route('admin.order.ship.preview', $order->order_code))
            ->assertStatus(422)
            ->assertJsonPath('abs_awb', 'ABS-EXISTING');
    }

    // ---- Permissions ---------------------------------------------------

    public function test_a_view_only_admin_cannot_preview_or_ship(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();
        $viewer = $this->adminWith('view orders');

        $this->actingAs($viewer)
            ->get(route('admin.order.ship.preview', $order->order_code))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    // ---- Shipping ------------------------------------------------------

    public function test_shipping_stores_the_awb_moves_delivery_status_and_logs_both(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertRedirect(route('admin.order.show', $order->order_code))
            ->assertSessionHas('success');

        $order->refresh();

        $this->assertSame('ABS-123456789', $order->abs_awb);
        $this->assertNotNull($order->abs_shipped_at);
        $this->assertSame(DeliveryStatusEnum::PROCESSING, $order->delivery_status);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_SHIPPED,
        ]);
        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_DELIVERY_STATUS_CHANGED,
        ]);

        $log = OrderLog::where('order_id', $order->id)
            ->where('action', OrderLog::ACTION_SHIPPED)
            ->firstOrFail();

        /* The address the parcel was actually booked to — the only way to tell
           later whether a misdelivery was a bad match or a bad address. */
        $this->assertSame(2, $log->new_values['governorate_id']);
        $this->assertSame(11, $log->new_values['city_id']);
        $this->assertSame('ABS-123456789', $log->new_values['abs_awb']);
    }

    public function test_the_confirmed_ids_are_what_reaches_abs(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();

        /* The admin overrode the matched city (11) with a different one. What
           the admin confirmed must win over what the order text says. */
        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 13,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'create-shipment')) {
                return false;
            }

            $body = $request->data();

            return $body['shipment']['cityId'] === 13
                && $body['shipment']['governorateId'] === 2
                && $body['cmpService'] === 'DELIVERY'
                /* The pickup rides along with the shipment — this is what
                   makes it one call rather than two. */
                && $body['locationId'] === 7
                /* Server-derived on their side; the spec says do not send. */
                && ! array_key_exists('awb', $body['shipment'])
                && ! array_key_exists('businessLocationId', $body['shipment'])
                && $request->header('x-api-key') === ['test-key'];
        });
    }

    public function test_shipping_without_a_city_is_refused(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
            ])
            ->assertSessionHasErrors('city_id');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'create-shipment'));
        $this->assertNull($order->refresh()->abs_awb);
    }

    public function test_an_order_that_already_shipped_is_never_booked_twice(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder(['abs_awb' => 'ABS-EXISTING', 'abs_shipped_at' => now()]);

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertSessionHasErrors('ship');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'create-shipment'));
        $this->assertSame('ABS-EXISTING', $order->refresh()->abs_awb);
    }

    public function test_a_trashed_order_is_never_shipped(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();
        $order->delete();

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertSessionHasErrors('ship');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'create-shipment'));
    }

    public function test_a_refusal_from_abs_leaves_the_order_completely_untouched(): void
    {
        $this->fakeAbs([
            '*/create-shipment' => Http::response([
                'success' => false,
                'message' => ['consigneeName should not be empty'],
                'error' => 'Bad Request',
            ], 400),
        ]);
        $order = $this->seedOrder();

        $response = $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ]);

        /* ABS's own words, so the admin knows which field to fix rather than
           having to ask us what the courier objected to. */
        $response->assertSessionHasErrors(['ship' => 'consigneeName should not be empty']);

        $order->refresh();
        $this->assertNull($order->abs_awb);
        $this->assertNull($order->abs_shipped_at);
        $this->assertSame(DeliveryStatusEnum::PENDING, $order->delivery_status);
        $this->assertDatabaseMissing('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_SHIPPED,
        ]);
    }

    public function test_a_success_carrying_no_awb_is_treated_as_a_failure(): void
    {
        $this->fakeAbs([
            '*/create-shipment' => Http::response(['success' => true, 'data' => null], 201),
        ]);
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertSessionHasErrors('ship');

        /* An order marked shipped with nothing to track is worse than one that
           visibly failed to ship. */
        $this->assertNull($order->refresh()->abs_awb);
    }

    // ---- The mapping itself --------------------------------------------

    public function test_cod_is_what_is_still_owed_on_a_cash_order(): void
    {
        $payload = app(AbsShipmentPayload::class);

        $order = $this->seedOrder(['total_amount' => 450.00, 'total_paid' => 100.00]);
        $this->assertSame(350.00, $payload->codAmount($order));

        /* Overpaid asks the courier for nothing, never for a negative amount. */
        $overpaid = $this->seedOrder([
            'order_code' => 'DL-OVERPAID',
            'total_amount' => 100.00,
            'total_paid' => 150.00,
        ]);
        $this->assertSame(0.0, $payload->codAmount($overpaid));
    }

    public function test_a_wallet_transfer_order_asks_the_courier_to_collect_nothing(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder([
            'payment_type' => PaymentTypeEnum::TRANSFER_WALLET,
            'total_paid' => 450.00,
        ]);

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'create-shipment')) {
                return false;
            }

            /* Zero must be SENT, not omitted: a prepaid order with no COD
               instruction at all is one the courier may charge for again.
               The key being present is half the assertion — hence the
               `array_key_exists` rather than reading it and comparing. */
            $shipment = $request->data()['shipment'];

            return array_key_exists('cash', $shipment)
                && (float) $shipment['cash'] === 0.0;
        });
    }

    public function test_local_phone_numbers_are_sent_in_e164(): void
    {
        $payload = app(AbsShipmentPayload::class);

        $this->assertSame('+201070274943', $payload->phone('01070274943'));
        $this->assertSame('+201070274943', $payload->phone('010 7027 4943'));
        $this->assertSame('+201070274943', $payload->phone('201070274943'));
        /* Already international — left alone, so a foreign number is never
           rewritten into an Egyptian one. */
        $this->assertSame('+971501234567', $payload->phone('+971 50 123 4567'));
        $this->assertNull($payload->phone(''));
        $this->assertNull($payload->phone(null));
    }

    public function test_arabic_place_names_match_across_their_spellings(): void
    {
        $matcher = app(AbsAddressMatcher::class);
        $options = [
            ['id' => 2, 'label' => 'الجيزة'],
            ['id' => 3, 'label' => 'الإسكندرية'],
        ];

        /* ta marbuta vs ha, the missing article, and the alef forms — all of
           them differences between what a buyer types and what a courier's
           database holds, none of them a different place. */
        $this->assertSame(2, $matcher->match('الجيزه', $options)['id']);
        $this->assertSame(2, $matcher->match('جيزة', $options)['id']);
        $this->assertSame(3, $matcher->match('الاسكندرية', $options)['id']);
        $this->assertNull($matcher->match('أسوان', $options));
        $this->assertNull($matcher->match('', $options));
    }

    public function test_an_ambiguous_name_matches_nothing_rather_than_the_first_row(): void
    {
        $matcher = app(AbsAddressMatcher::class);

        /* Two rows both contain "أكتوبر", so the text does not identify a
           place. Guessing between them sends a parcel somewhere nobody chose. */
        $this->assertNull($matcher->match('أكتوبر', [
            ['id' => 11, 'label' => 'السادس من أكتوبر'],
            ['id' => 12, 'label' => 'حدائق أكتوبر'],
        ]));

        /* One row contains it, so it is unambiguous and does match. */
        $this->assertSame(11, $matcher->match('أكتوبر', [
            ['id' => 11, 'label' => 'السادس من أكتوبر'],
            ['id' => 13, 'label' => 'الدقي'],
        ])['id']);
    }

    public function test_contents_are_read_from_the_archived_line_names(): void
    {
        $this->fakeAbs();
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->post(route('admin.order.ship', $order->order_code), [
                'governorate_id' => 2,
                'city_id' => 11,
            ])
            ->assertRedirect();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'create-shipment')) {
                return false;
            }

            $shipment = $request->data()['shipment'];

            return $shipment['contents'] === 'جهاز قياس الضغط'
                && $shipment['noOfPcs'] === 2
                && $shipment['itemValue'] === 450.0;
        });
    }

    public function test_the_button_is_hidden_when_no_abs_key_is_configured(): void
    {
        config(['services.abs.key' => null]);
        $order = $this->seedOrder();

        $this->actingAs($this->adminWith('manage orders'))
            ->get(route('admin.order.show', $order->order_code))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('absConfigured', false));
    }
}
