<?php

namespace Tests\Feature;

use App\Actions\Membership\CreateRelative;
use App\Enums\Order\PaymentStatusEnum;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\RelativeFee;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** A relative costs 5 EGP a month. An individual pays the year at once and the relative is active at once for a year; a company is only asked to call. */
class RelativeFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSettings::put('payment_cod_enabled', true, Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
    }

    private function member(string $kind = Membership::KIND_INDIVIDUAL): array
    {
        $user = User::factory()->create(['name' => 'Ahmed Ali Hassan', 'gender' => 'male', 'phone' => '01062587475']);
        $card = Membership::factory()->create([
            'user_id' => $user->id, 'is_active' => true, 'is_visible' => true, 'plan' => Membership::PLAN_FULL, 'member_kind' => $kind, 'expiration_date' => now()->addMonths(6),
        ]);
        MemberPayment::query()->create(['membership_id' => $card->id, 'amount' => 700, 'months_paid' => 12, 'from_date' => now()->toDateString(), 'to_date' => now()->addMonths(12)->toDateString(), 'type' => 'cash']);

        return [$user, $card];
    }

    private function pendingRelative(Membership $card, string $name = 'Mona Ahmed'): Membership
    {
        return app(CreateRelative::class)->handle($card, ['name' => $name, 'relationship' => 'wife', 'phone' => '01099990000'], Membership::ADDED_BY_MEMBER);
    }

    /** @test */
    public function the_fee_is_five_a_month_for_twelve_months_from_the_settings(): void
    {
        [$user, $card] = $this->member();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/relative-fees')->assertOk()
            ->assertJsonPath('data.fee.monthly', 5)->assertJsonPath('data.fee.months', 12)->assertJsonPath('data.fee.per_relative', 60)->assertJsonPath('data.fee.request_only', false);

        SiteSettings::put(RelativeFee::MONTHLY, 7, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $this->getJson('/api/v1/mobile/relative-fees')->assertJsonPath('data.fee.per_relative', 84);
    }

    /** @test */
    public function an_individual_pays_the_year_and_the_relative_is_active_for_a_year_once_paid(): void
    {
        [$user, $card] = $this->member();
        $relative = $this->pendingRelative($card);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/relative-fees')->assertJsonPath('data.waiting.0.id', $relative->id);

        $res = $this->postJson('/api/v1/mobile/relative-fees/orders', ['relative_ids' => [$relative->id], 'payment_type' => 'cod'])
            ->assertCreated()->assertJsonPath('order.total_amount', 60);
        $order = Order::query()->where('order_code', $res->json('order.order_code'))->firstOrFail();

        $this->assertSame(RelativeFee::SOURCE, $order->source);
        $this->assertFalse((bool) $relative->fresh()->is_active, 'not active before the money is accepted');

        $order->update(['payment_status' => PaymentStatusEnum::ACCEPTED]);

        $fresh = $relative->fresh();
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame('approved', $fresh->approval_status instanceof \BackedEnum ? $fresh->approval_status->value : $fresh->approval_status);
        $this->assertTrue($fresh->expiration_date->between(now()->addMonths(12)->subDay(), now()->addMonths(12)->addDay()));

        // Nothing left to pay for, and a second try changes nothing.
        $this->getJson('/api/v1/mobile/relative-fees')->assertJsonPath('data.waiting', []);
        $this->assertSame(0, RelativeFee::activateFor($order->fresh()));
    }

    /** @test */
    public function a_relative_whose_own_year_ended_is_expired_even_if_the_card_is_not(): void
    {
        [, $card] = $this->member();
        $relative = $this->pendingRelative($card);
        $relative->forceFill(['is_active' => true, 'fee_order_id' => 1, 'expiration_date' => now()->subDay()])->save();

        $this->assertFalse($relative->fresh()->isCurrent());
        $this->assertSame(Membership::STATE_EXPIRED, $relative->fresh()->state());
    }

    /** @test */
    public function a_company_card_pays_nothing_and_the_team_gets_an_important_call_request(): void
    {
        [$user, $card] = $this->member(Membership::KIND_COMPANY);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/mobile/relative-fees')->assertJsonPath('data.fee.request_only', true)->assertJsonPath('data.fee.kind', 'company');

        $res = $this->post('/api/v1/family/members', [
            'name' => 'Mona Ahmed Ali', 'relationship' => 'wife', 'national_id' => '29001010101011', 'phone' => '01099990000',
            'id_front' => UploadedFile::fake()->image('f.jpg'), 'id_back' => UploadedFile::fake()->image('b.jpg'), 'photo' => UploadedFile::fake()->image('p.jpg'),
        ], ['Accept' => 'application/json']);
        $this->assertSame(201, $res->status(), $res->getContent());
        $res->assertJsonPath('data.relative_fee.request_only', true);

        $this->assertDatabaseHas('contact_messages', ['source' => 'relative_request']);
        $this->assertStringContainsString('IMPORTANT', (string) \App\Models\ContactMessage::query()->where('source', 'relative_request')->value('subject'));
    }
}
