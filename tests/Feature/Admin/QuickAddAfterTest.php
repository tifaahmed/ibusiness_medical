<?php

namespace Tests\Feature\Admin;

use App\Models\MemberExtraPhone;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** After a quick add: the next-steps page, the WhatsApp page, extra numbers (notes only) and the WhatsApp text permissions. */
class QuickAddAfterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $perms = []): User
    {
        $u = User::factory()->create();
        foreach (array_merge(['manage memberships'], $perms) as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $u;
    }

    private function quickAdd(User $by)
    {
        return $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('admin.user.membership.quick-add'), ['name' => 'Omar Ali', 'phone' => '01012345678', 'member_kind' => 'company', 'wants_card' => true]);
    }

    public function test_after_adding_the_admin_lands_on_the_next_steps_page_and_the_whatsapp_page_has_everything(): void
    {
        Http::fake();
        $by = $this->admin();
        $res = $this->quickAdd($by);
        $m = Membership::query()->whereNotNull('quick_added_at')->firstOrFail();
        $slug = $m->user->slug;
        $res->assertRedirect(route('admin.user.membership.quick-add.done', $slug));

        $done = $this->actingAs($by)->get(route('admin.user.membership.quick-add.done', $slug))->assertOk()->viewData('page')['props'];
        $this->assertSame($m->membership_number, $done['member']['membership_number']);
        $this->assertNotNull($done['member']['sms']);

        $wa = $this->actingAs($by)->get(route('admin.user.membership.quick-add.whatsapp', $slug))->assertOk()->viewData('page')['props'];
        $msg = $wa['message'];
        $this->assertStringContainsString('Omar Ali', $msg);
        $this->assertStringContainsString($m->membership_number, $msg);          // membership number
        $this->assertStringContainsString('/membership/'.$m->membership_number.'?slug='.$m->membership_number, $msg); // card link carries the number
        $this->assertStringContainsString('play.google.com', $msg);               // full android link
        $this->assertStringContainsString('apps.apple.com', $msg);                // full iPhone link
        $this->assertStringNotContainsString('deilar.com/app', $msg); // WhatsApp gets the full links, not the short one
        $this->assertStringContainsString('مندوبنا', $msg);                       // wanted a card
        $this->assertFalse($wa['can_edit_message']);                              // read only without the permission
        $this->assertSame('2010'.'12345678', $wa['wa_own']);
    }

    public function test_extra_numbers_are_notes_many_per_member_and_removable(): void
    {
        Http::fake();
        $by = $this->admin();
        $this->quickAdd($by);
        $m = Membership::query()->whereNotNull('quick_added_at')->firstOrFail();
        $slug = $m->user->slug;
        $post = fn (array $b) => $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.user.membership.extra-phones.store', $slug), $b);

        $post(['phone' => '01199999999', 'note' => 'WhatsApp'])->assertSessionHasNoErrors();
        $post(['phone' => '01288888888'])->assertSessionHasNoErrors();
        $post(['phone' => '+20 119 999 9999'])->assertSessionHasNoErrors();      // same number written differently: not stored twice
        $post(['phone' => '123'])->assertSessionHasErrors('phone');

        $this->assertSame(2, MemberExtraPhone::where('user_id', $m->user_id)->count());
        $this->assertSame('01012345678', $m->user->fresh()->phone);               // the member's own phone is untouched
        $this->assertSame('201199999999', MemberExtraPhone::where('phone', '01199999999')->firstOrFail()->whatsappDigits());

        // Another member with the SAME extra number is fine (it is only a note).
        $other = User::factory()->create();
        MemberExtraPhone::create(['user_id' => $other->id, 'phone' => '01199999999']);

        $row = MemberExtraPhone::where('user_id', $m->user_id)->first();
        $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->delete(route('admin.user.membership.extra-phones.destroy', [$slug, $row->id]))->assertRedirect();
        $this->assertSame(1, MemberExtraPhone::where('user_id', $m->user_id)->count());
        $this->assertSoftDeleted($row);                                           // removed, not destroyed
    }

    public function test_whatsapp_texts_follow_the_templates_and_have_their_own_permissions(): void
    {
        $edit = $this->admin(['edit whatsapp customer message', 'edit whatsapp message templates']);
        $plain = $this->admin();
        Http::fake();
        $this->quickAdd($plain);
        $slug = Membership::query()->whereNotNull('quick_added_at')->firstOrFail()->user->slug;

        $this->assertTrue($this->actingAs($edit)->get(route('admin.user.membership.quick-add.whatsapp', $slug))->viewData('page')['props']['can_edit_message']);

        // The template drives the text.
        Setting::where('slug', QuickAdd::WA_COMPANY)->update(['value' => "مرحباً {name}\n*{app}*"]);
        SiteSettings::forget();
        $msg = $this->actingAs($plain)->get(route('admin.user.membership.quick-add.whatsapp', $slug))->viewData('page')['props']['message'];
        $this->assertStringStartsWith("مرحباً Omar Ali\n*ديلر*", $msg);

        // WhatsApp-templates-only admin: edits the wa_msg row, not the SMS row, not an ordinary setting.
        $waOnly = $this->admin(['edit whatsapp message templates']);
        $row = Setting::where('slug', QuickAdd::WA_CARD)->firstOrFail();
        $sms = Setting::where('slug', QuickAdd::MSG_CARD)->firstOrFail();
        $put = fn (Setting $s, string $v) => $this->actingAs($waOnly)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('admin.setting.update', $s->id), ['slug' => $s->slug, 'name' => $s->getTranslations('name'), 'value_type' => 'text', 'value' => $v]);
        $put($row, "سطر أول\nسطر ثاني")->assertRedirect();
        $this->assertSame("سطر أول\nسطر ثاني", $row->fresh()->value);
        $put($sms, 'x')->assertForbidden();
    }
}
