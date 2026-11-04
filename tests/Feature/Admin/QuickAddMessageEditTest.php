<?php

namespace Tests\Feature\Admin;

use App\Models\Membership;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Two permissions: rewrite ONE customer's message, and edit the general templates (settings rows). */
class QuickAddMessageEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $perms): User
    {
        $u = User::factory()->create();
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }

        return $u;
    }

    private function add(User $by, array $over = [])
    {
        return $this->actingAs($by)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->post(route('admin.user.membership.quick-add'), $over + [
            'name' => 'Omar', 'phone' => '01012345678', 'device' => 'android', 'member_kind' => 'company', 'wants_card' => false,
        ]);
    }

    public function test_the_templates_are_seeded_and_drive_the_message(): void
    {
        $this->assertSame(QuickAdd::templateDefaults()[QuickAdd::MSG_COMPANY], Setting::where('slug', QuickAdd::MSG_COMPANY)->value('value'));

        Setting::where('slug', QuickAdd::MSG_COMPANY)->update(['value' => 'مرحباً بشركتكم في {app} — {days} مجاناً']);
        SiteSettings::forget();

        $this->assertStringContainsString('مرحباً بشركتكم في ديلر — 30 يوماً مجاناً', QuickAdd::message('company', 'android', false));

        // Sentences are joined by one space, whatever spaces the stored text has.
        Setting::where('slug', QuickAdd::MSG_COMPANY)->update(['value' => QuickAdd::templateDefaults()[QuickAdd::MSG_COMPANY]]);
        SiteSettings::forget();
        $this->assertStringContainsString('يتم الخصم. مندوبنا', QuickAdd::message('company', 'android', true));

        // A blanked template falls back to the built-in wording: never an empty SMS.
        Setting::where('slug', QuickAdd::MSG_COMPANY)->update(['value' => '']);
        SiteSettings::forget();
        $this->assertStringContainsString('أهلاً بك في ديلر! لديك تجربة مجانية', QuickAdd::message('company', 'android', false));
    }

    public function test_one_customers_message_needs_its_permission(): void
    {
        Http::fake();
        $plain = $this->admin(['manage memberships']);

        $this->add($plain, ['message_override' => 'نص مخصص'])->assertSessionHasErrors('message_override');
        $this->assertSame(0, Membership::query()->whereNotNull('quick_added_at')->count());

        $editor = $this->admin(['manage memberships', 'edit quick add customer message']);
        $this->add($editor, ['message_override' => 'أهلاً يا عمر، حسابك جاهز'])->assertSessionHasNoErrors();
        $this->assertSame('أهلاً يا عمر، حسابك جاهز', SmsMessage::firstOrFail()->body);
    }

    public function test_template_rows_are_editable_only_with_the_templates_permission(): void
    {
        $tpl = Setting::where('slug', QuickAdd::MSG_CARD)->firstOrFail();
        $other = Setting::query()->whereNotIn('slug', QuickAdd::templateSlugs())->firstOrFail();
        $put = fn (User $u, Setting $s, array $body) => $this->actingAs($u)->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->put(route('admin.setting.update', $s->id), $body);

        $templatesOnly = $this->admin(['edit quick add message templates']);
        $put($templatesOnly, $tpl, ['slug' => 'hacked', 'name' => ['en' => 'x', 'ar' => 'x'], 'value_type' => 'text', 'value' => 'نص جديد للكارت'])->assertRedirect();
        $tpl->refresh();
        $this->assertSame('نص جديد للكارت', $tpl->value);
        $this->assertSame(QuickAdd::MSG_CARD, $tpl->slug);                 // the key could not be changed
        $put($templatesOnly, $other, ['slug' => $other->slug, 'name' => $other->getTranslations('name'), 'value_type' => $other->value_type, 'value' => 'x'])->assertForbidden();

        // `manage settings` alone does not open the message texts.
        $settingsOnly = $this->admin(['manage settings']);
        $put($settingsOnly, $tpl, ['slug' => $tpl->slug, 'name' => $tpl->getTranslations('name'), 'value_type' => 'text', 'value' => 'no'])->assertForbidden();

        // The list for a templates-only admin shows just the message rows.
        $res = $this->actingAs($templatesOnly)->get(route('admin.setting.list'))->assertOk();
        $slugs = collect($res->viewData('page')['props']['settings']['data'])->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(QuickAdd::templateSlugs(), $slugs);
    }
}
