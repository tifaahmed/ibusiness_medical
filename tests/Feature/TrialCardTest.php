<?php

namespace Tests\Feature;

use App\Enums\Contact\ContactSourceEnum;
use App\Enums\User\UserRoleEnum;
use App\Mail\SubscriptionReceiptMail;
use App\Models\ContactMessage;
use App\Models\FamilyMember;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\MembershipSubscription;
use App\Models\OrderPaymentLog;
use App\Models\Setting;
use App\Models\TrialClaim;
use App\Models\User;
use App\Services\Paymob\PaymobHmac;
use App\Support\AdminAlerts;
use App\Support\MembershipPlan;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Self-service trial cards, their expiry, paying for them, and the relatives rules.
 *
 * Behind Http::fake() throughout — nothing here may reach Paymob. Run on a
 * throwaway database (see AGENTS.md).
 */
class TrialCardTest extends TestCase
{
    use RefreshDatabase;

    private const HMAC = 'trial-hmac-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.paymob.secret_key' => 'egy_sk_test',
            'services.paymob.public_key' => 'egy_pk_test',
            'services.paymob.hmac' => self::HMAC,
            'services.paymob.integration_ids' => ['111'],
            'services.paymob.currency' => 'EGP',
            'services.paymob.base_url' => 'https://accept.paymob.com',
            'services.paymob.allowed_return_urls' => ['https://shop.test/'],
        ]);

        SiteSettings::put(MembershipPlan::PRICE, 600, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::MONTHS, 12, Setting::TYPE_NUMBER);
        SiteSettings::put(MembershipPlan::TRIAL_MONTHS, 1, Setting::TYPE_NUMBER);
    }

    private function member(string $phone = '01012345678'): User
    {
        $user = User::factory()->create(['phone' => $phone, 'name' => null]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function startTrial(array $overrides = [])
    {
        return $this->postJson('/api/v1/membership/trial', array_merge([
            'name' => 'Mona Hassan',
            'national_id' => '29001011234567',
        ], $overrides));
    }

    private function trialCard(string $phone = '01012345678', string $nid = '29001011234567'): array
    {
        $user = $this->member($phone);
        $this->startTrial(['national_id' => $nid])->assertCreated();

        return [$user->refresh(), $user->memberships()->latest('id')->first()];
    }

    // ---- Creating the trial --------------------------------------------

    public function test_a_national_id_can_be_checked_before_the_trial_is_requested(): void
    {
        $this->trialCard('01012345678', '29001011234567');

        $this->member('01122334455');

        $this->postJson('/api/v1/membership/trial/check-national-id', ['national_id' => '29001011234567'])
            ->assertOk()->assertJsonPath('data.available', false);
        $this->postJson('/api/v1/membership/trial/check-national-id', ['national_id' => '29102021234567'])
            ->assertOk()->assertJsonPath('data.available', true);
        $this->postJson('/api/v1/membership/trial/check-national-id', ['national_id' => '11111111111111'])
            ->assertUnprocessable();
    }

    public function test_a_trial_card_needs_only_a_name_and_an_id_and_lasts_a_month(): void
    {
        $user = $this->member();

        $response = $this->startTrial()->assertCreated()
            ->assertJsonPath('data.state', 'trial')
            ->assertJsonPath('data.is_trial', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.relatives.must_contact_support', true)
            ->assertJsonPath('data.relatives.max', 15)
            ->assertJsonPath('data.relatives.can_add', false);

        $membership = $user->memberships()->firstOrFail();

        $this->assertSame(Membership::PLAN_TRIAL, $membership->plan);
        $this->assertTrue($membership->is_active && $membership->is_visible);
        $this->assertNotNull($membership->completed_at, 'the admin list hides rows with no completed_at');
        $this->assertTrue($membership->expiration_date->between(now()->addDays(27), now()->addDays(32)));
        $this->assertSame('29001011234567', $membership->national_id);
        $this->assertSame('Mona Hassan', $user->fresh()->name);
        $this->assertSame($membership->membership_number, $response->json('data.membership_number'));
        $this->assertDatabaseHas('trial_claims', ['national_id' => '29001011234567', 'phone' => '01012345678', 'membership_id' => $membership->id]);
        $this->assertSame(0, $membership->familyMembers()->count());
    }

    public function test_the_trial_saves_the_gender_when_one_is_sent(): void
    {
        $user = $this->member();

        $this->startTrial(['gender' => 'female'])->assertCreated();
        $this->assertSame('female', $user->fresh()->gender);
    }

    public function test_an_unknown_gender_is_rejected(): void
    {
        $this->member();

        $this->startTrial(['gender' => 'robot'])->assertUnprocessable();
    }

    public function test_phone_check_tells_a_new_number_from_a_card_holder_without_sending_a_code(): void
    {
        [, $membership] = $this->trialCard('01012345678');
        $placeholder = User::factory()->create(['phone' => '01199999999']);
        $placeholder->memberships()->create(['membership_number' => 'REG-ABC123', 'slug' => 'REG-ABC123', 'is_active' => false, 'is_visible' => false]);

        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '01012345678'])->assertOk()->assertJsonPath('data.has_card', true);
        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '01199999999'])->assertOk()->assertJsonPath('data.has_card', false);
        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '01555555555'])->assertOk()->assertJsonPath('data.has_card', false);
        $this->postJson('/api/v1/mobile/auth/phone-check', ['phone' => '123'])->assertUnprocessable();
        $this->assertNotNull($membership);
    }

    private function adminWith(string $permission = 'manage memberships'): User
    {
        $admin = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-trial', 'web');
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        $admin->assignRole($role);

        return $admin;
    }

    public function test_an_admin_can_take_the_trial_mark_off_by_hand_and_the_expiry_is_kept(): void
    {
        [$user, $membership] = $this->trialCard();
        $expiry = $membership->expiration_date->toDateTimeString();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->adminWith())->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.user.membership.remove-trial', $user->slug))
            ->assertRedirect()->assertSessionHas('success');

        $membership->refresh();
        $this->assertSame(Membership::PLAN_FULL, $membership->plan);
        $this->assertFalse($membership->isTrial());
        $this->assertSame($expiry, $membership->expiration_date->toDateTimeString(), 'only the mark changes');
        $this->assertDatabaseHas('member_logs', ['membership_id' => $membership->id]);

        // Idempotent: a second press changes nothing.
        $this->post(route('admin.user.membership.remove-trial', $user->slug))->assertRedirect();
        $this->assertSame(Membership::PLAN_FULL, $membership->fresh()->plan);
    }

    public function test_removing_the_trial_needs_the_membership_permission(): void
    {
        [$user, $membership] = $this->trialCard();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->adminWith('view memberships'))->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.user.membership.remove-trial', $user->slug))
            ->assertForbidden();

        $this->assertSame(Membership::PLAN_TRIAL, $membership->fresh()->plan);
    }

    public function test_confirming_a_payment_removes_the_trial_on_its_own(): void
    {
        [, $membership] = $this->trialCard();
        $subscription = MembershipSubscription::query()->create([
            'membership_id' => $membership->id, 'method' => 'vodafone_cash', 'amount' => 600, 'months' => 12, 'status' => MembershipSubscription::STATUS_PENDING,
        ]);

        $this->assertTrue(app(\App\Actions\Membership\ActivateSubscription::class)->handle($subscription, $this->adminWith()->id));

        $membership->refresh();
        $this->assertSame(Membership::PLAN_FULL, $membership->plan);
        $this->assertFalse($membership->isTrial());
        $this->assertTrue((bool) $membership->is_paid);
    }

    public function test_a_phone_signup_placeholder_is_upgraded_not_duplicated(): void
    {
        $user = $this->member();
        $pending = Membership::query()->create([
            'user_id' => $user->id, 'membership_number' => 'REG-ABCDEF1234', 'slug' => 'pending-x',
            'registration_date' => now(), 'is_active' => false, 'is_visible' => false,
        ]);

        $this->startTrial()->assertCreated();

        $this->assertSame(1, $user->memberships()->count());
        $this->assertSame($pending->id, $user->memberships()->first()->id);
        $this->assertStringStartsNotWith('REG-', $user->memberships()->first()->membership_number);
    }

    public function test_the_id_number_cannot_get_a_second_card_from_another_phone(): void
    {
        $this->trialCard('01012345678', '29001011234567');

        $this->member('01123456789');
        $this->startTrial(['national_id' => '29001011234567'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'national_id_taken');

        $this->assertSame(1, Membership::query()->count());
    }

    public function test_the_phone_cannot_get_a_second_card_with_another_id(): void
    {
        $this->trialCard('01012345678', '29001011234567');

        // Same phone, new account row — as an import or a second device login could leave behind.
        $second = User::factory()->create(['phone' => '+201012345678', 'name' => null]);
        Sanctum::actingAs($second);

        $this->startTrial(['national_id' => '29505051234567'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'phone_taken');
    }

    public function test_the_same_account_on_another_device_just_sees_its_card(): void
    {
        [$user] = $this->trialCard();

        $this->startTrial()->assertUnprocessable()->assertJsonPath('reason', 'already_has_card');
        $this->getJson('/api/v1/membership')->assertOk()->assertJsonPath('data.state', 'trial');
        $this->assertSame(1, $user->memberships()->count());
    }

    public function test_deleting_the_card_does_not_hand_back_a_free_trial(): void
    {
        [$user, $membership] = $this->trialCard();
        $membership->forceDelete();

        $this->member('01199999999');
        $this->startTrial(['national_id' => '29001011234567'])->assertUnprocessable()->assertJsonPath('reason', 'national_id_taken');

        $this->assertSame(1, TrialClaim::query()->count());
    }

    public function test_an_id_already_on_a_relative_is_refused(): void
    {
        $owner = User::factory()->create();
        $card = Membership::query()->create(['user_id' => $owner->id, 'membership_number' => '5555', 'slug' => '5555', 'registration_date' => now(), 'is_active' => true, 'is_visible' => true]);
        FamilyMember::query()->create(['membership_id' => $card->id, 'name' => 'Existing Son', 'relationship' => 'son', 'national_id' => '30001011234567', 'is_active' => true]);

        $this->member();
        $this->startTrial(['national_id' => '30001011234567'])->assertUnprocessable()->assertJsonPath('reason', 'national_id_taken');
    }

    public function test_a_malformed_id_is_rejected(): void
    {
        $this->member();
        $this->startTrial(['national_id' => '123'])->assertUnprocessable()->assertJsonValidationErrors('national_id');
    }

    // ---- Expiry & the scan ---------------------------------------------

    public function test_after_the_trial_the_scan_says_expired_and_offers_every_way_to_pay_or_call(): void
    {
        [, $membership] = $this->trialCard();
        SiteSettings::put('contact_phone_international', '+201020709993', Setting::TYPE_PHONE);
        $membership->forceFill(['expiration_date' => now()->subDay()])->save();

        $this->getJson("/api/v1/card/{$membership->membership_number}/status")
            ->assertOk()
            ->assertJsonPath('data.state', 'trial_expired')
            ->assertJsonPath('data.expired', true)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.can_pay_online', true)
            ->assertJsonPath('data.price', 600)
            ->assertJsonPath('data.contact.phone_international', '+201020709993');

        // The same truth on the page a facility or the owner opens from the QR code.
        $this->get(route('guest.membership.show', $membership->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('membership.status.expired', true)->where('membership.status.state', 'trial_expired'));

        // ...and in the app's card endpoint.
        $this->getJson("/api/v1/mobile/card/{$membership->membership_number}")
            ->assertOk()->assertJsonPath('status.expired', true);

        $this->assertFalse(Membership::earnsMemberPrice($membership->membership_number), 'an expired trial must not earn the member price');
    }

    public function test_the_buy_button_is_hidden_when_nothing_is_for_sale_online(): void
    {
        [, $membership] = $this->trialCard();
        SiteSettings::put(MembershipPlan::PRICE, 0, Setting::TYPE_NUMBER);

        $this->getJson("/api/v1/card/{$membership->membership_number}/status")
            ->assertJsonPath('data.can_pay_online', false);

        $this->postJson("/api/v1/card/{$membership->membership_number}/subscribe")->assertUnprocessable();
    }

    // ---- Paying --------------------------------------------------------

    private function fakeIntention(): void
    {
        Http::fake(['*/v1/intention/' => Http::response(['id' => 'int_9', 'client_secret' => 'cs_sub'], 201)]);
    }

    private function signed(MembershipSubscription $sub, array $overrides = []): array
    {
        $obj = array_replace_recursive([
            'id' => 7001, 'pending' => false, 'amount_cents' => (int) round($sub->amount * 100), 'success' => true,
            'is_auth' => false, 'is_capture' => false, 'is_standalone_payment' => true, 'is_voided' => false, 'is_refunded' => false,
            'is_3d_secure' => true, 'integration_id' => 111, 'has_parent_transaction' => false, 'currency' => 'EGP',
            'created_at' => '2026-10-20T10:00:00', 'error_occured' => false, 'owner' => 1,
            'order' => ['id' => 88, 'merchant_order_id' => $sub->paymob_reference],
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'data' => ['txn_response_code' => 'APPROVED', 'message' => 'Approved'],
        ], $overrides);

        return ['obj' => $obj, 'hmac' => PaymobHmac::compute($obj, self::HMAC)];
    }

    private function sendCallback(array $signed, ?string $hmac = null)
    {
        return $this->postJson('/api/v1/paymob/callback?hmac='.($hmac ?? $signed['hmac']), ['type' => 'TRANSACTION', 'obj' => $signed['obj']]);
    }

    public function test_paying_online_from_the_scan_page_activates_a_full_year_and_mails_the_receipt(): void
    {
        Mail::fake();
        [$user, $membership] = $this->trialCard();
        $user->forceFill(['email' => 'mona@example.com'])->save();
        $membership->forceFill(['expiration_date' => now()->subDay()])->save();
        $this->fakeIntention();

        // Public: the owner may be looking at a red sign on a card scanned without being signed in.
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/v1/card/{$membership->membership_number}/subscribe", ['return_url' => 'https://shop.test/card'])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', fn ($url) => str_contains($url, 'clientSecret=cs_sub'));

        $sub = MembershipSubscription::query()->firstOrFail();
        $this->assertSame('SUB'.$sub->id.'A1', $sub->paymob_reference);

        Http::assertSent(fn ($request) => ($request->data()['extras']['kind'] ?? null) === 'membership'
            && ($request->data()['extras']['membership_number'] ?? null) === $membership->membership_number
            && ($request->data()['extras']['subscription_id'] ?? null) === $sub->id);
        $this->assertSame('600.00', $sub->amount);
        $this->assertSame($sub->id, OrderPaymentLog::query()->where('event', 'intention_create')->value('membership_subscription_id'));

        $signed = $this->signed($sub);
        $this->sendCallback($signed)->assertOk();
        $this->sendCallback($signed)->assertOk(); // Paymob retries

        $membership->refresh();
        $this->assertSame(Membership::PLAN_FULL, $membership->plan);
        $this->assertTrue($membership->is_paid);
        $this->assertTrue($membership->expiration_date->between(now()->addMonths(12)->subDay(), now()->addMonths(12)->addDay()), 'paid from today, once');
        $this->assertSame(Membership::STATE_ACTIVE, $membership->state());
        $this->assertSame(1, MemberPayment::query()->where('membership_id', $membership->id)->count(), 'a repeated callback must not pay twice');
        $this->assertSame(MembershipSubscription::STATUS_ACCEPTED, $sub->fresh()->status);
        $this->assertSame('7001', $sub->fresh()->paymob_transaction_id);
        Mail::assertSent(SubscriptionReceiptMail::class, 1);
    }

    public function test_a_forged_or_wrong_amount_callback_extends_nothing(): void
    {
        [, $membership] = $this->trialCard();
        $this->fakeIntention();
        $this->postJson("/api/v1/card/{$membership->membership_number}/subscribe")->assertCreated();
        $sub = MembershipSubscription::query()->firstOrFail();

        $this->sendCallback($this->signed($sub), 'deadbeef')->assertForbidden();
        $this->sendCallback($this->signed($sub, ['amount_cents' => 100]))->assertOk();

        $this->assertSame(Membership::PLAN_TRIAL, $membership->fresh()->plan);
        $this->assertSame(MembershipSubscription::STATUS_PENDING, $sub->fresh()->status);
    }

    public function test_a_declined_card_can_still_succeed_on_the_next_try(): void
    {
        [, $membership] = $this->trialCard();
        $this->fakeIntention();
        $this->postJson("/api/v1/card/{$membership->membership_number}/subscribe")->assertCreated();
        $sub = MembershipSubscription::query()->firstOrFail();

        $this->sendCallback($this->signed($sub, ['success' => false]))->assertOk();
        $this->assertSame(MembershipSubscription::STATUS_REJECTED, $sub->fresh()->status);

        $this->sendCallback($this->signed($sub, ['id' => 7002]))->assertOk();
        $this->assertSame(MembershipSubscription::STATUS_ACCEPTED, $sub->fresh()->status);
    }

    public function test_a_transfer_needs_a_receipt_and_waits_for_an_admin_who_then_extends_the_card(): void
    {
        Storage::fake('public');
        [, $membership] = $this->trialCard();

        $this->postJson('/api/v1/membership/subscribe', ['method' => 'vodafone_cash'])->assertUnprocessable();

        $this->post('/api/v1/membership/subscribe', [
            'method' => 'vodafone_cash',
            'reference' => '01099999999',
            'receipts' => [UploadedFile::fake()->image('receipt.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $sub = MembershipSubscription::query()->firstOrFail();
        $this->assertSame(MembershipSubscription::STATUS_PENDING, $sub->status);
        $this->assertCount(1, $sub->getMedia(MembershipSubscription::RECEIPT_COLLECTION));
        $this->assertSame(Membership::PLAN_TRIAL, $membership->fresh()->plan, 'a receipt is a claim, not a payment');
        $this->assertDatabaseHas('contact_messages', ['source' => ContactSourceEnum::SUBSCRIPTION_REQUEST->value]);

        $admin = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-sub', 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));
        $admin->assignRole($role);

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->get(route('admin.subscription.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('subscriptions.data', 1)->where('subscriptions.data.0.receipts.0.name', 'receipt.jpg'));

        $this->post(route('admin.subscription.confirm', $sub->id))->assertRedirect()->assertSessionHas('success');

        $membership->refresh();
        $this->assertSame(Membership::PLAN_FULL, $membership->plan);
        $this->assertSame($admin->id, $sub->fresh()->confirmed_by);
        $this->assertSame('vodafone_cash', MemberPayment::query()->first()->type);

        // Confirming twice never adds a second year.
        $until = $membership->expiration_date->toDateString();
        $this->post(route('admin.subscription.confirm', $sub->id))->assertSessionHas('error');
        $this->assertSame($until, $membership->fresh()->expiration_date->toDateString());
    }

    public function test_cash_on_delivery_rings_customer_services(): void
    {
        $this->trialCard();

        $this->postJson('/api/v1/membership/subscribe', ['method' => 'cash_on_delivery'])->assertCreated();

        $this->assertDatabaseHas('contact_messages', ['source' => 'subscription_request', 'phone' => '01012345678']);
        $this->assertSame(MembershipSubscription::METHOD_COD, MembershipSubscription::query()->value('method'));
    }

    public function test_the_subscription_queue_is_closed_to_admins_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.subscription.index'))->assertForbidden();
    }

    // ---- Relatives -----------------------------------------------------

    private function addRelative(array $overrides = [])
    {
        Storage::fake('local');
        Storage::fake('public');

        return $this->post('/api/v1/family/members', array_merge([
            'name' => 'Ahmed',
            'relationship' => 'son',
            'national_id' => (string) random_int(30000000000000, 30999999999999),
            'id_front' => UploadedFile::fake()->image('f.jpg'),
            'id_back' => UploadedFile::fake()->image('b.jpg'),
            'photo' => UploadedFile::fake()->image('p.jpg'),
        ], $overrides), ['Accept' => 'application/json']);
    }

    public function test_a_trial_member_cannot_add_relatives_but_can_ask_to_be_called_once(): void
    {
        $this->trialCard();

        $this->addRelative()->assertForbidden()->assertJsonPath('reason', 'trial_no_relatives');
        $this->assertSame(0, FamilyMember::query()->count());

        $this->postJson('/api/v1/membership/relatives-request')->assertOk()->assertJsonPath('data.already_open', false);
        $this->postJson('/api/v1/membership/relatives-request')->assertOk()->assertJsonPath('data.already_open', true);

        $this->assertSame(1, ContactMessage::query()->where('source', ContactSourceEnum::RELATIVE_REQUEST->value)->count());
        $this->assertSame(1, AdminAlerts::counts()['relative_requests']);
    }

    private function paidCard(): Membership
    {
        [, $membership] = $this->trialCard();
        $membership->forceFill(['plan' => Membership::PLAN_FULL, 'expiration_date' => now()->addYear()])->save();

        return $membership;
    }

    public function test_a_paid_member_adds_a_first_degree_relative_who_waits_for_approval_and_the_admin_hears_of_it(): void
    {
        $membership = $this->paidCard();

        $this->addRelative(['relationship' => 'mother'])->assertCreated();

        $relative = FamilyMember::query()->firstOrFail();
        $this->assertSame('pending', $relative->approval_status->value);
        $this->assertFalse($relative->is_active);
        $this->assertSame('member', $relative->added_by);
        $this->assertDatabaseHas('contact_messages', ['source' => ContactSourceEnum::PENDING_RELATIVE->value]);

        Cache::flush();
        $this->assertSame(1, AdminAlerts::counts()['pending_relatives']);
        $this->assertSame(1, $membership->familyMembers()->where('approval_status', 'pending')->count());
    }

    public function test_siblings_are_not_first_degree(): void
    {
        $this->paidCard();

        $this->addRelative(['relationship' => 'brother'])->assertUnprocessable()->assertJsonValidationErrors('relationship');
    }

    public function test_a_card_carries_at_most_fifteen_relatives(): void
    {
        $membership = $this->paidCard();

        foreach (range(1, 15) as $i) {
            FamilyMember::query()->create([
                'membership_id' => $membership->id, 'name' => "Rel $i", 'relationship' => 'son',
                'national_id' => (string) (31000000000000 + $i), 'is_active' => true,
            ]);
        }

        $this->addRelative()->assertUnprocessable()->assertJsonPath('reason', 'limit_reached');
    }

    public function test_an_expired_card_adds_no_relatives(): void
    {
        $membership = $this->paidCard();
        $membership->forceFill(['expiration_date' => now()->subDay()])->save();

        $this->addRelative()->assertForbidden()->assertJsonPath('reason', 'card_expired');
    }

    public function test_the_member_list_can_be_filtered_to_cards_with_pending_relatives(): void
    {
        $withPending = $this->paidCard();
        FamilyMember::query()->create(['membership_id' => $withPending->id, 'name' => 'Wait Son', 'relationship' => 'son', 'national_id' => '32000000000001', 'is_active' => false, 'approval_status' => 'pending', 'added_by' => 'member']);

        $other = User::factory()->create(['name' => 'No Pending']);
        Membership::query()->create(['user_id' => $other->id, 'membership_number' => '7777', 'slug' => '7777', 'registration_date' => now(), 'is_active' => true, 'is_visible' => true, 'completed_at' => now()]);

        $admin = User::factory()->create();
        $role = Role::findOrCreate(UserRoleEnum::ADMIN.'-list', 'web');
        $role->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));
        $admin->assignRole($role);

        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->get(route('admin.user.membership.list', ['pending_relatives' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.membership.pending_relatives', 1));
    }

    /** A card whose owner has one subscription in the given state, method and channel. */
    private function cardWithSubscription(string $phone, string $nid, string $method, string $channel, string $status = MembershipSubscription::STATUS_ACCEPTED): array
    {
        [$user, $membership] = $this->trialCard($phone, $nid);
        $this->app['auth']->forgetGuards();

        $subscription = $membership->subscriptions()->create([
            'method' => $method, 'channel' => $channel, 'status' => $status, 'amount' => 600, 'months' => 12,
            'reference' => '0100'.substr($phone, -7), 'paid_at' => $status === MembershipSubscription::STATUS_ACCEPTED ? now() : null,
        ]);

        return [$user, $membership, $subscription];
    }

    public function test_the_member_list_filters_members_who_paid_by_method_and_from_the_app(): void
    {
        $this->cardWithSubscription('01011111111', '29001010000011', MembershipSubscription::METHOD_PAYMOB, 'mobile');
        $this->cardWithSubscription('01022222222', '29001010000022', MembershipSubscription::METHOD_VODAFONE, 'mobile');
        $this->cardWithSubscription('01033333333', '29001010000033', MembershipSubscription::METHOD_INSTAPAY, 'website');
        $this->cardWithSubscription('01044444444', '29001010000044', MembershipSubscription::METHOD_VODAFONE, 'mobile', MembershipSubscription::STATUS_PENDING);
        $this->trialCard('01055555555', '29001010000055'); // never paid

        $admin = $this->adminWith();
        $count = fn (array $query) => $this->actingAs($admin)->get(route('admin.user.membership.list', $query))->assertOk()->viewData('page')['props']['users']['data'];

        $this->assertCount(3, $count(['paid_via' => 'any']), 'every confirmed payment; a pending receipt and a trial that never paid are not "paid"');
        $this->assertCount(2, $count(['paid_app' => 1]), 'only payments made from the mobile app');
        $this->assertCount(1, $count(['paid_via' => 'paymob']));
        $this->assertCount(1, $count(['paid_via' => 'instapay']));
        $this->assertCount(1, $count(['paid_via' => 'vodafone_cash']), 'the pending Vodafone receipt does not count');
        $this->assertCount(1, $count(['paid_via' => 'vodafone_cash', 'paid_app' => 1]));
        $this->assertCount(0, $count(['paid_via' => 'instapay', 'paid_app' => 1]), 'that Instapay payment came from the website');
        $this->assertCount(0, $count(['paid_via' => 'cash_on_delivery']));
        $this->assertCount(5, $count(['paid_via' => 'nonsense']), 'an unknown value is ignored, not a filter');
    }

    public function test_the_show_page_lists_the_subscription_payments_with_their_receipts(): void
    {
        Storage::fake('public');
        [$user, $membership, $subscription] = $this->cardWithSubscription('01011111111', '29001010000011', MembershipSubscription::METHOD_INSTAPAY, 'mobile');
        $subscription->addMedia(UploadedFile::fake()->image('receipt.jpg'))->toMediaCollection(MembershipSubscription::RECEIPT_COLLECTION);
        $subscription->forceFill(['confirmed_by' => $this->adminWith()->id, 'confirmed_at' => now()])->save();

        $this->actingAs($this->adminWith())->get(route('admin.user.membership.show', $user->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('user.memberships.0.plan', Membership::PLAN_TRIAL)
                ->has('user.memberships.0.subscriptions', 1)
                ->where('user.memberships.0.subscriptions.0.method', 'instapay')
                ->where('user.memberships.0.subscriptions.0.channel', 'mobile')
                ->where('user.memberships.0.subscriptions.0.status', 'accepted')
                ->where('user.memberships.0.subscriptions.0.amount', 600)
                ->where('user.memberships.0.subscriptions.0.receipts.0.name', 'receipt.jpg')
                ->where('user.memberships.0.subscriptions.0.confirmed_by', fn ($name) => filled($name)));
    }

    public function test_a_payment_remembers_whether_it_came_from_the_app_or_the_website(): void
    {
        Storage::fake('public');
        [, $membership] = $this->trialCard();

        $this->withHeader('User-Agent', 'okhttp/4.9 Deilar-Android')->post('/api/v1/membership/subscribe', [
            'method' => 'instapay', 'reference' => '010', 'receipts' => [UploadedFile::fake()->image('a.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('mobile', $membership->subscriptions()->latest('id')->value('channel'));

        $membership->subscriptions()->delete();

        $this->withHeader('User-Agent', 'GuzzleHttp/7.8 PHP/8.3')->post('/api/v1/membership/subscribe', [
            'method' => 'vodafone_cash', 'reference' => '010', 'receipts' => [UploadedFile::fake()->image('b.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('website', $membership->subscriptions()->latest('id')->value('channel'));
    }

    public function test_the_member_status_reports_how_complete_the_profile_is(): void
    {
        $this->trialCard();

        $this->getJson('/api/v1/membership')
            ->assertOk()
            ->assertJsonPath('data.progress.overall.total', 10)
            ->assertJson(fn ($json) => $json->has('data.progress.overall.percent')->has('data.progress.overall.missing')->etc());
    }
}
