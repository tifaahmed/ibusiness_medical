<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use App\Support\SiteSettings;
use App\Support\WalletWithdrawals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Cashing wallet money out to Vodafone Cash through Paymob Payouts (Paymob is faked: no real call is ever made). */
class WalletWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://payouts.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['services.paymob_payouts' => ['base_url' => self::HOST, 'client_id' => 'cid', 'client_secret' => 'sec', 'username' => 'u', 'password' => 'p']]);
        Cache::flush();
        SiteSettings::put(WalletWithdrawals::ENABLED, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
    }

    private function member(float $wallet = 500): User
    {
        $user = User::factory()->create(['phone' => '01011111111']);
        WalletTransaction::query()->create(['user_id' => $user->id, 'source' => 'admin', 'client_id' => 'seed'.uniqid(), 'amount' => $wallet]);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));

        return $admin;
    }

    private function fakePaymob(array $disburse, int $status = 200): void
    {
        Http::fake([
            self::HOST.'/api/secure/o/token/' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            self::HOST.'/api/secure/disburse/' => Http::response($disburse, $status),
            self::HOST.'/api/secure/transaction/inquire/' => Http::response(['count' => 0, 'results' => []]),
        ]);
    }

    private function ask(User $user, float $amount = 100, string $msisdn = '01012345678', string $nid = '29005270102927')
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/wallet/withdrawals', ['amount' => $amount, 'msisdn' => $msisdn, 'national_id' => $nid]);
    }

    /** @test */
    public function it_is_off_by_default_and_when_paymob_is_not_set_up(): void
    {
        $user = $this->member();
        SiteSettings::put(WalletWithdrawals::ENABLED, '0', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
        $this->ask($user)->assertStatus(422)->assertJsonPath('reason', 'disabled');

        SiteSettings::put(WalletWithdrawals::ENABLED, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
        config(['services.paymob_payouts.client_secret' => null]);
        $this->ask($user)->assertStatus(422)->assertJsonPath('reason', 'disabled');
        $this->assertSame(500.0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function a_request_takes_the_money_out_of_the_wallet_at_once_and_waits_for_approval(): void
    {
        Http::fake();
        $user = $this->member();

        $this->ask($user, 120)->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.msisdn', '0101••••678');

        $this->assertSame(380.0, WalletTransaction::balanceOf($user->id));
        Http::assertNothingSent(); // nothing goes to Paymob before an admin approves
        $this->assertSame('29005270102927', WalletWithdrawal::query()->first()->national_id);
        $this->assertStringNotContainsString('29005270102927', (string) \DB::table('wallet_withdrawals')->value('national_id')); // stored encrypted
    }

    /** @test */
    public function the_wallet_never_goes_below_zero_and_the_rules_are_enforced(): void
    {
        $user = $this->member(100);

        $this->ask($user, 150)->assertStatus(422)->assertJsonPath('reason', 'not_enough');
        $this->ask($user, 10)->assertStatus(422)->assertJsonPath('reason', 'bad_amount');
        $this->ask($user, 5000)->assertStatus(422)->assertJsonPath('reason', 'bad_amount');
        $this->ask($user, 60, '01112345678')->assertStatus(422)->assertJsonPath('reason', 'bad_number'); // not Vodafone
        $this->ask($user, 60, '01012345678', '123')->assertStatus(422)->assertJsonPath('reason', 'bad_national_id');
        $this->assertSame(100.0, WalletTransaction::balanceOf($user->id));

        $this->ask($user, 100)->assertCreated();
        $this->assertSame(0.0, WalletTransaction::balanceOf($user->id));
        $this->ask($user, 50)->assertStatus(422)->assertJsonPath('reason', 'open_request'); // one at a time
        $this->assertSame(0.0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function the_daily_cap_counts_what_was_already_requested(): void
    {
        $user = $this->member(20000);
        SiteSettings::put(WalletWithdrawals::DAILY_MAX, 2500, Setting::TYPE_NUMBER);
        SiteSettings::forget();
        $this->fakePaymob(['disbursement_status' => 'success', 'status_code' => '200', 'transaction_id' => 't1']);
        $admin = $this->admin();

        $first = $this->ask($user, 2000)->assertCreated()->json('data.id');
        WalletWithdrawals::approve(WalletWithdrawal::find($first), $admin->id);
        $this->ask($user, 600)->assertStatus(422)->assertJsonPath('reason', 'daily_limit');
        $this->ask($user, 500)->assertCreated();
    }

    /** @test */
    public function an_admin_approval_pays_through_paymob_once_even_if_pressed_twice(): void
    {
        $this->fakePaymob(['disbursement_status' => 'success', 'status_code' => '200', 'status_description' => 'amount is deposited successfully', 'transaction_id' => 'abc-1']);
        $user = $this->member();
        $admin = $this->admin();
        $id = $this->ask($user, 100)->assertCreated()->json('data.id');
        $w = WalletWithdrawal::find($id);

        $this->assertTrue(WalletWithdrawals::approve($w, $admin->id));
        $this->assertFalse(WalletWithdrawals::approve($w, $admin->id));

        $row = $w->fresh();
        $this->assertSame('paid', $row->status);
        $this->assertSame('abc-1', $row->provider_transaction_id);
        $this->assertNotNull($row->paid_at);
        $this->assertSame(400.0, WalletTransaction::balanceOf($user->id)); // debited once, never refunded
        Http::assertSentCount(2); // one login + ONE disbursement
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/disburse/') && $r['issuer'] === 'vodafone' && $r['msisdn'] === '01012345678'
            && $r['amount'] === '100.00' && $r['national_id'] === '29005270102927' && $r['client_reference_id'] === $row->client_reference
            && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    /** @test */
    public function a_refusal_by_paymob_gives_the_money_back_once(): void
    {
        $this->fakePaymob(['disbursement_status' => 'failed', 'status_code' => '90040', 'status_description' => 'not subscribed', 'transaction_id' => 'x']);
        $user = $this->member();
        $admin = $this->admin();
        $w = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));

        WalletWithdrawals::approve($w, $admin->id);
        WalletWithdrawals::refresh($w, $admin->id);
        WalletWithdrawals::approve($w, $admin->id);

        $this->assertSame('failed', $w->fresh()->status);
        $this->assertSame('90040', $w->fresh()->provider_code);
        $this->assertSame(500.0, WalletTransaction::balanceOf($user->id));
        $this->assertSame(1, WalletTransaction::query()->where('client_id', 'withdrawal-refund:'.$w->id)->count());
    }

    /** @test */
    public function a_timeout_never_refunds_on_a_guess_and_a_later_check_settles_it(): void
    {
        Http::fake([
            self::HOST.'/api/secure/o/token/' => Http::response(['access_token' => 'tok']),
            self::HOST.'/api/secure/disburse/' => Http::response('gateway timeout', 504),
        ]);
        $user = $this->member();
        $admin = $this->admin();
        $w = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));

        WalletWithdrawals::approve($w, $admin->id);
        $this->assertSame('processing', $w->fresh()->status);
        $this->assertSame(400.0, WalletTransaction::balanceOf($user->id)); // money NOT given back: it may have left

        Http::fake([
            self::HOST.'/api/secure/o/token/' => Http::response(['access_token' => 'tok']),
            self::HOST.'/api/secure/transaction/inquire/' => Http::response(['count' => 1, 'results' => [['transaction_id' => 'z9', 'disbursement_status' => 'successful', 'status_code' => '200']]]),
        ]);
        $this->assertSame('paid', WalletWithdrawals::refresh($w, $admin->id));
        $this->assertSame(400.0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function no_record_at_paymob_gives_the_money_back_only_after_the_grace_period(): void
    {
        Http::fake([
            self::HOST.'/api/secure/o/token/' => Http::response(['access_token' => 'tok']),
            self::HOST.'/api/secure/disburse/' => Http::response('', 500),
            self::HOST.'/api/secure/transaction/inquire/' => Http::response(['count' => 0, 'results' => []]),
        ]);
        $user = $this->member();
        $admin = $this->admin();
        $w = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));
        WalletWithdrawals::approve($w, $admin->id);

        $this->assertSame('processing', WalletWithdrawals::refresh($w, $admin->id)); // too soon to say "never sent"
        $this->assertSame(400.0, WalletTransaction::balanceOf($user->id));

        $w->forceFill(['decided_at' => now()->subMinutes(30)])->save();
        $this->assertSame('failed', WalletWithdrawals::refresh($w, $admin->id));
        $this->assertSame(500.0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function a_login_failure_sends_nothing_and_returns_the_money(): void
    {
        Http::fake([self::HOST.'/api/secure/o/token/' => Http::response(['error' => 'invalid_grant'], 400)]);
        $user = $this->member();
        $admin = $this->admin();
        $w = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));

        WalletWithdrawals::approve($w, $admin->id);

        $this->assertSame('failed', $w->fresh()->status);
        $this->assertSame(500.0, WalletTransaction::balanceOf($user->id));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/disburse/'));
    }

    /** @test */
    public function a_pending_answer_stays_processing_until_paymob_confirms(): void
    {
        $this->fakePaymob(['disbursement_status' => 'pending', 'status_code' => '200', 'transaction_id' => 'p1']);
        $user = $this->member();
        $w = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));
        WalletWithdrawals::approve($w, $this->admin()->id);

        $this->assertSame('processing', $w->fresh()->status);
        $this->assertSame(400.0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function rejecting_or_cancelling_while_waiting_returns_the_money_once_and_a_sent_one_cannot_be_cancelled(): void
    {
        $this->fakePaymob(['disbursement_status' => 'pending', 'status_code' => '200']);
        $user = $this->member();
        $admin = $this->admin();

        $a = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/wallet/withdrawals/{$a->id}/cancel")->assertOk()->assertJsonPath('data.status', 'canceled');
        $this->postJson("/api/v1/wallet/withdrawals/{$a->id}/cancel")->assertStatus(422);
        $this->assertSame(500.0, WalletTransaction::balanceOf($user->id));

        $b = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));
        $this->assertTrue(WalletWithdrawals::reject($b, $admin->id, 'wrong number'));
        $this->assertFalse(WalletWithdrawals::reject($b, $admin->id));
        $this->assertSame(500.0, WalletTransaction::balanceOf($user->id));

        $c = WalletWithdrawal::find($this->ask($user, 100)->json('data.id'));
        WalletWithdrawals::approve($c, $admin->id);
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/wallet/withdrawals/{$c->id}/cancel")->assertStatus(422);
        $this->assertFalse(WalletWithdrawals::reject($c, $admin->id));
        $this->assertSame(400.0, WalletTransaction::balanceOf($user->id));
    }

    /** @test */
    public function another_member_cannot_cancel_it_and_the_list_never_shows_the_national_id(): void
    {
        Http::fake();
        $owner = $this->member();
        $w = WalletWithdrawal::find($this->ask($owner, 100)->json('data.id'));

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/wallet/withdrawals/{$w->id}/cancel")->assertNotFound();

        Sanctum::actingAs($owner);
        $json = $this->getJson('/api/v1/wallet/withdrawals')->assertOk();
        $this->assertStringNotContainsString('29005270102927', $json->getContent());
        $json->assertJsonPath('data.rules.enabled', true)->assertJsonPath('data.withdrawals.0.status', 'pending');
    }

    /** @test */
    public function with_auto_on_the_request_is_paid_at_once_and_the_admin_page_is_top_admin_only(): void
    {
        $this->fakePaymob(['disbursement_status' => 'success', 'status_code' => '200', 'transaction_id' => 'a1']);
        SiteSettings::put(WalletWithdrawals::AUTO, '1', Setting::TYPE_BOOLEAN);
        SiteSettings::forget();
        $user = $this->member();
        $this->ask($user, 100)->assertCreated()->assertJsonPath('data.status', 'paid');

        $this->withoutVite();
        $plain = User::factory()->create();
        $this->actingAs($plain)->withoutMiddleware(\Illuminate\Auth\Middleware\Authorize::class)->get(route('admin.withdrawals.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.withdrawals.index'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/WalletWithdrawal/Index')->has('rows', 1)->where('rows.0.status', 'paid'));
    }
}
