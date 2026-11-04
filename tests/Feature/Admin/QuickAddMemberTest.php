<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\MembershipPlan;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Quick add: stamped for good, trial per kind, message queued while the switch is off, no number/price in the text. */
class QuickAddMemberTest extends TestCase
{
    use RefreshDatabase;

    private function sales(): User
    {
        $u = User::factory()->create(['name' => 'Sally Sales']);
        $u->givePermissionTo(Permission::findOrCreate('manage memberships', 'web'));

        return $u;
    }

    private function quickAdd(User $by, array $over = [])
    {
        return $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.user.membership.quick-add'), $over + [
            'name' => 'Omar', 'phone' => '01012345678', 'device' => 'android', 'member_kind' => 'individual', 'wants_card' => false,
        ]);
    }

    public function test_individual_gets_seven_days_and_a_queued_message_while_sending_is_off(): void
    {
        Http::fake();
        $by = $this->sales();

        $this->quickAdd($by, ['wants_card' => true])->assertRedirect()->assertSessionHasNoErrors();

        $m = Membership::query()->whereNotNull('quick_added_at')->firstOrFail();
        $this->assertSame($by->id, $m->quick_added_by);
        $this->assertSame('individual', $m->member_kind);
        $this->assertSame('android', $m->quick_device);
        $this->assertTrue($m->quick_wants_card);
        $this->assertSame(7, (int) round(now()->diffInDays($m->expiration_date, true)));

        $sms = SmsMessage::firstOrFail();
        $this->assertSame('queued', $sms->status);
        $this->assertStringContainsString('تجربة مجانية 7 أيام', $sms->body);
        $this->assertStringContainsString('مندوبنا سيتواصل معك قريباً لتوصيل الكارت', $sms->body);
        $this->assertStringNotContainsString($m->membership_number, $sms->body); // the customer signs in with the phone
        $this->assertStringNotContainsString('جنيه', $sms->body);                // and no prices
        $this->assertSame(mb_strlen($sms->body), $sms->characters);
        Http::assertNothingSent();
    }

    public function test_linked_admin_files_under_own_sales_rep_and_lands_on_member_edit(): void
    {
        Http::fake();
        $by = $this->sales();
        $mine = \App\Models\Sales::create(['name' => ['ar' => 'مندوبي', 'en' => 'Mine'], 'admin_user_id' => $by->id]);
        $other = \App\Models\Sales::create(['name' => ['ar' => 'غيره', 'en' => 'Other']]);

        // The browser tries to name another rep: ignored.
        $res = $this->quickAdd($by, ['sales_id' => $other->id]);

        $m = Membership::query()->whereNotNull('quick_added_at')->firstOrFail();
        $this->assertSame($mine->id, (int) $m->sales_id);
        $res->assertRedirect(route('admin.user.membership.quick-add.done', $m->user->slug));
    }

    public function test_one_admin_one_sales_rep(): void
    {
        $a = User::factory()->create();
        $s1 = \App\Models\Sales::create(['name' => ['ar' => 'أ', 'en' => 'A']]);
        $s2 = \App\Models\Sales::create(['name' => ['ar' => 'ب', 'en' => 'B']]);

        \App\Models\Sales::linkAdmin($a->id, $s1->id);
        \App\Models\Sales::linkAdmin($a->id, $s2->id); // moves, never two

        $this->assertNull($s1->fresh()->admin_user_id);
        $this->assertSame($a->id, (int) $s2->fresh()->admin_user_id);
        $this->assertNotContains($s2->id, array_column(\App\Models\Sales::linkableOptions(999999), 'value'));
    }

    public function test_taken_phone_is_refused_with_the_holder_named(): void
    {
        Http::fake();
        $by = $this->sales();
        $this->quickAdd($by, ['name' => 'First Owner'])->assertSessionHasNoErrors();
        $card = Membership::query()->whereNotNull('quick_added_at')->firstOrFail();

        $res = $this->quickAdd($by, ['name' => 'Second']);

        $res->assertSessionHasErrors('phone');
        $msg = session('errors')->first('phone');
        $this->assertStringContainsString('First Owner', $msg);
        $this->assertStringContainsString($card->membership_number, $msg);
        $this->assertSame(1, Membership::query()->whereNotNull('quick_added_at')->count());
    }

    public function test_preview_is_shown_empty_and_fills_in_as_choices_are_made(): void
    {
        $by = $this->sales();
        $get = fn (array $q) => $this->actingAs($by)->getJson(route('admin.user.membership.quick-add.preview', $q))->assertOk()->json();

        $empty = $get([]);
        $this->assertFalse($empty['complete']);
        $this->assertStringContainsString('[', $empty['body']);

        $part = $get(['member_kind' => 'company']);
        $this->assertStringContainsString('أهلاً بك في ديلر! لديك تجربة مجانية', $part['body']);
        $this->assertStringContainsString('إذا وافقتم على المتابعة', $part['body']);
        $this->assertStringContainsString('[رابط التطبيق', $part['body']);

        $full = $get(['member_kind' => 'company', 'device' => 'android']);
        $this->assertTrue($full['complete']);
        $this->assertStringNotContainsString('[', $full['body']);
    }

    public function test_phone_check_answers_as_soon_as_eleven_digits_are_in(): void
    {
        Http::fake();
        $by = $this->sales();
        $this->quickAdd($by, ['name' => 'Holder One'])->assertSessionHasNoErrors();
        $get = fn (string $p) => $this->actingAs($by)->getJson(route('admin.user.membership.quick-add.phone-check', ['phone' => $p]))->assertOk()->json();

        $this->assertFalse($get('0101234')['valid']);                       // not 11 digits yet: nothing to say
        $this->assertTrue($get('01012345678')['taken']);                    // the one just added
        $this->assertStringContainsString('Holder One', $get('01012345678')['message']);
        $free = $get('01099999999');
        $this->assertSame('Holder One', $get('01012345678')['member']['name']);          // who holds it
        $this->assertStringContainsString('/admin/user/membership/', (string) $get('01012345678')['member']['url']); // the button to their profile
        $this->assertNull($get('01099999999')['member']);
        $this->assertTrue($free['valid']);
        $this->assertFalse($free['taken']);
    }

    public function test_company_gets_thirty_days_and_the_company_text(): void
    {
        $this->quickAdd($this->sales(), ['member_kind' => 'company', 'device' => 'iphone'])->assertRedirect();
        $m = Membership::query()->whereNotNull('quick_added_at')->firstOrFail();
        $this->assertSame('company', $m->member_kind);
        $this->assertSame(30, (int) round(now()->diffInDays($m->expiration_date, true)));
        $this->assertStringContainsString('أهلاً بك في ديلر! لديك تجربة مجانية', SmsMessage::firstOrFail()->body);
    }

    public function test_kind_is_required_and_a_taken_phone_is_refused(): void
    {
        $by = $this->sales();
        $this->quickAdd($by, ['member_kind' => ''])->assertSessionHasErrors('member_kind');
        $this->quickAdd($by)->assertSessionHasNoErrors();
        $this->quickAdd($by, ['name' => 'Again'])->assertSessionHasErrors('phone');
        $this->assertSame(1, Membership::whereNotNull('quick_added_at')->count());
    }

    public function test_sms_panel_is_for_the_super_admin_only(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales)->get(route('admin.sms.index'))->assertForbidden();
        $this->actingAs($sales)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->put(route('admin.sms.toggle'), ['enabled' => true])->assertForbidden();

        $boss = User::factory()->create();
        $boss->assignRole(Role::findOrCreate(UserRoleEnum::SUPER_ADMIN, 'web'));
        $this->actingAs($boss)->get(route('admin.sms.index'))->assertOk();
        $this->actingAs($boss)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->put(route('admin.sms.toggle'), ['enabled' => true])->assertRedirect();
        $this->assertTrue(QuickAdd::smsEnabled());
    }

    public function test_prices_per_kind_and_the_kind_decides_the_plans(): void
    {
        $this->assertSame(['year', 'half', 'month'], array_column(MembershipPlan::plans('individual'), 'key'));
        $this->assertSame(['year', 'month'], array_column(MembershipPlan::plans('company'), 'key'));
        SiteSettings::put(MembershipPlan::COMPANY_MONTH_PRICE, '50');
        SiteSettings::put(MembershipPlan::INDIVIDUAL_MONTH_PRICE, '100');
        $this->assertSame(50.0, MembershipPlan::find('month', 'company')['price']);
        $this->assertSame(100.0, MembershipPlan::find('month', 'individual')['price']);
        $this->assertNull(MembershipPlan::find('half', 'company')); // companies are not sold the half-year plan
    }

    public function test_preview_returns_the_exact_text_that_would_be_sent(): void
    {
        $by = $this->sales();
        $preview = $this->actingAs($by)->getJson(route('admin.user.membership.quick-add.preview', ['device' => 'android', 'member_kind' => 'individual', 'wants_card' => 1]))->assertOk()->json();

        $this->quickAdd($by, ['wants_card' => true])->assertRedirect();
        $this->assertSame(SmsMessage::firstOrFail()->body, $preview['body']);
        $this->assertSame(mb_strlen($preview['body']), $preview['characters']);
        $this->assertFalse($preview['sending']);
        $this->actingAs($by)->getJson(route('admin.user.membership.quick-add.preview', ['device' => 'x', 'member_kind' => 'individual']))->assertStatus(422);
    }
}
