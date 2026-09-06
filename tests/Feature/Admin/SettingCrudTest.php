<?php

namespace Tests\Feature\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The settings CRUD in the admin: who may reach it, what a row may hold, and
 * what happens to an uploaded image when the row changes or goes away.
 */
class SettingCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('settings-tester', 'web');

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user->assignRole($role);

        return $user;
    }

    private function writer(): User
    {
        return $this->admin(UserPermissionEnum::MANAGE_SETTINGS);
    }

    private function setting(array $attributes = []): Setting
    {
        return Setting::create(array_merge([
            'slug' => 'deilar_phone',
            'name' => ['en' => 'Phone number', 'ar' => 'رقم الهاتف'],
            'value' => '01020709993',
            'value_type' => Setting::TYPE_PHONE,
        ], $attributes));
    }

    public function test_the_list_is_reachable_with_the_read_only_permission(): void
    {
        $this->setting();

        $this->actingAs($this->admin(UserPermissionEnum::VIEW_SETTINGS))
            ->get(route('admin.setting.list'))
            ->assertOk();
    }

    public function test_a_read_only_admin_cannot_write(): void
    {
        $setting = $this->setting();
        $viewer = $this->admin(UserPermissionEnum::VIEW_SETTINGS);

        $this->actingAs($viewer)->get(route('admin.setting.create'))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.setting.destroy', $setting->id))->assertForbidden();
    }

    public function test_an_admin_without_either_permission_is_shut_out(): void
    {
        $this->actingAs($this->admin(UserPermissionEnum::MANAGE_FACILITIES))
            ->get(route('admin.setting.list'))
            ->assertForbidden();
    }

    public function test_a_setting_is_created_and_readable_by_its_key(): void
    {
        $this->actingAs($this->writer())
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar_whatsapp',
                'name' => ['en' => 'WhatsApp', 'ar' => 'واتساب'],
                'value' => '01020709993',
                'value_type' => Setting::TYPE_PHONE,
            ])
            ->assertRedirect(route('admin.setting.list'));

        $this->assertDatabaseHas('settings', ['slug' => 'deilar_whatsapp', 'value' => '01020709993']);
        // The cache is dropped by the model's own events, so the new row reads
        // back immediately rather than after the next deploy.
        $this->assertSame('01020709993', SiteSettings::get('deilar_whatsapp'));
    }

    public function test_a_key_is_normalised_and_has_to_be_unique(): void
    {
        $this->setting();
        $writer = $this->writer();

        // Upper case and stray spaces are slips, not errors.
        $this->actingAs($writer)
            ->post(route('admin.setting.store'), [
                'slug' => '  Deilar_Fax  ',
                'name' => ['en' => 'Fax', 'ar' => 'فاكس'],
                'value_type' => Setting::TYPE_STRING,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['slug' => 'deilar_fax']);

        $this->actingAs($writer)
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar_phone',
                'name' => ['en' => 'Phone again', 'ar' => 'هاتف'],
                'value_type' => Setting::TYPE_STRING,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_key_with_spaces_or_symbols_is_refused(): void
    {
        $this->actingAs($this->writer())
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar phone!',
                'name' => ['en' => 'Phone', 'ar' => 'هاتف'],
                'value_type' => Setting::TYPE_STRING,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_value_has_to_match_the_type_it_declares(): void
    {
        $writer = $this->writer();

        foreach ([
            [Setting::TYPE_URL, 'not a url'],
            [Setting::TYPE_EMAIL, 'not an email'],
            [Setting::TYPE_NUMBER, 'twelve'],
            [Setting::TYPE_JSON, '{broken'],
        ] as $index => [$type, $value]) {
            $this->actingAs($writer)
                ->post(route('admin.setting.store'), [
                    'slug' => 'bad_'.$index,
                    'name' => ['en' => 'Bad', 'ar' => 'خطأ'],
                    'value' => $value,
                    'value_type' => $type,
                ])
                ->assertSessionHasErrors('value');
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_a_blank_value_is_stored_as_null_so_defaults_apply(): void
    {
        $this->actingAs($this->writer())
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar_fax',
                'name' => ['en' => 'Fax', 'ar' => 'فاكس'],
                'value' => '   ',
                'value_type' => Setting::TYPE_STRING,
            ])
            ->assertRedirect();

        $this->assertNull(Setting::where('slug', 'deilar_fax')->value('value'));
        $this->assertSame('fallback', SiteSettings::get('deilar_fax', 'fallback'));
    }

    public function test_a_setting_is_updated(): void
    {
        $setting = $this->setting();

        $this->actingAs($this->writer())
            ->put(route('admin.setting.update', $setting->id), [
                'slug' => 'deilar_phone',
                'name' => ['en' => 'Phone number', 'ar' => 'رقم الهاتف'],
                'value' => '0227704400',
                'value_type' => Setting::TYPE_PHONE,
            ])
            ->assertRedirect(route('admin.setting.list'));

        $this->assertSame('0227704400', $setting->fresh()->value);
        $this->assertSame('0227704400', SiteSettings::get('deilar_phone'));
    }

    public function test_an_image_setting_stores_the_upload_and_reads_back_as_a_url(): void
    {
        Storage::fake('public');

        $this->actingAs($this->writer())
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar_logo',
                'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
                'value_type' => Setting::TYPE_IMAGE,
                'value_image' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect();

        $setting = Setting::where('slug', 'deilar_logo')->firstOrFail();

        $this->assertStringStartsWith(Setting::IMAGE_DIRECTORY.'/', $setting->value);
        Storage::disk('public')->assertExists($setting->value);
        // The value is a path; what callers get is a browsable URL.
        $this->assertStringContainsString($setting->value, SiteSettings::get('deilar_logo'));
    }

    public function test_the_uploaded_logo_becomes_the_logo_every_page_shows(): void
    {
        Storage::fake('public');
        $writer = $this->writer();

        // Nothing uploaded yet: the shared prop still names the bundled asset.
        $this->actingAs($writer)->get(route('admin.setting.list'))
            ->assertInertia(fn (Assert $page) => $page->where('appLogo', asset(config('app.logo'))));

        $this->actingAs($writer)->post(route('admin.setting.store'), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        $path = Setting::where('slug', 'deilar_logo')->value('value');

        $this->actingAs($writer)->get(route('admin.setting.list'))
            ->assertInertia(fn (Assert $page) => $page->where('appLogo', Storage::disk('public')->url($path)));
    }

    public function test_clearing_the_logo_falls_back_to_the_bundled_asset(): void
    {
        Storage::fake('public');
        $writer = $this->writer();

        $this->actingAs($writer)->post(route('admin.setting.store'), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('logo.png'),
        ]);

        $setting = Setting::where('slug', 'deilar_logo')->firstOrFail();

        // The browser posts the update as a spoofed PUT so the file can ride
        // along; clearing the preview sends the delete flag instead.
        $this->actingAs($writer)->post(route('admin.setting.update', $setting->id), [
            '_method' => 'PUT',
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value' => '',
            'value_type' => Setting::TYPE_IMAGE,
            'value_image_delete' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($setting->fresh()->value);

        $this->actingAs($writer)->get(route('admin.setting.list'))
            ->assertInertia(fn (Assert $page) => $page->where('appLogo', asset(config('app.logo'))));
    }

    public function test_replacing_an_image_deletes_the_file_it_replaced(): void
    {
        Storage::fake('public');
        $writer = $this->writer();

        $this->actingAs($writer)->post(route('admin.setting.store'), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('first.png'),
        ]);

        $setting = Setting::where('slug', 'deilar_logo')->firstOrFail();
        $first = $setting->value;

        $this->actingAs($writer)->put(route('admin.setting.update', $setting->id), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('second.png'),
        ]);

        $second = $setting->fresh()->value;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_file_that_is_not_one_of_the_accepted_image_types_is_refused(): void
    {
        Storage::fake('public');

        // An SVG is a document that can carry script. Laravel's `image` rule
        // lets one through, so the narrower mime list is what stops it.
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs($this->writer())
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar_logo',
                'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
                'value_type' => Setting::TYPE_IMAGE,
                'value_image' => $svg,
            ])
            ->assertSessionHasErrors('value_image');

        $this->assertSame(0, Setting::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_oversized_image_is_refused_by_a_limit_the_server_can_honour(): void
    {
        Storage::fake('public');

        // Never advertise a limit PHP here would drop before validation runs.
        $limit = Setting::maxUploadKilobytes();
        $this->assertLessThanOrEqual(Setting::IMAGE_MAX_KILOBYTES, $limit);

        $this->actingAs($this->writer())
            ->post(route('admin.setting.store'), [
                'slug' => 'deilar_logo',
                'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
                'value_type' => Setting::TYPE_IMAGE,
                'value_image' => UploadedFile::fake()->create('huge.png', $limit + 1, 'image/png'),
            ])
            ->assertSessionHasErrors(['value_image' => 'The image may be at most '.round($limit / 1024, 1).' MB.']);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_rejected_update_leaves_the_stored_image_alone(): void
    {
        Storage::fake('public');
        $writer = $this->writer();

        $this->actingAs($writer)->post(route('admin.setting.store'), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('logo.png'),
        ]);

        $setting = Setting::where('slug', 'deilar_logo')->firstOrFail();
        $stored = $setting->value;

        // A key already taken by another row: the save never happens, so the
        // image the row still points at has to survive untouched.
        $this->setting(['slug' => 'deilar_phone']);

        $this->actingAs($writer)->post(route('admin.setting.update', $setting->id), [
            '_method' => 'PUT',
            'slug' => 'deilar_phone',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasErrors('slug');

        $this->assertSame($stored, $setting->fresh()->value);
        Storage::disk('public')->assertExists($stored);
    }

    public function test_a_missing_settings_table_does_not_take_the_site_down(): void
    {
        $writer = $this->writer();

        // What a deploy looks like in the moments before its migrations run.
        Schema::drop('settings');
        SiteSettings::forget();

        $this->assertSame('fallback', SiteSettings::get('deilar_phone', 'fallback'));
        $this->assertFalse(SiteSettings::has('deilar_logo'));

        // Pages still render, on the logo the application ships with.
        $this->actingAs($writer)->get(route('admin.setting.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('appLogo', asset(config('app.logo'))));
    }

    public function test_a_row_pointing_at_a_bundled_asset_never_deletes_that_file(): void
    {
        Storage::fake('public');
        // A seeded logo names a file shipped under public/, not an upload.
        $setting = $this->setting([
            'slug' => 'deilar_logo',
            'value' => 'images/logo/dielar.png',
            'value_type' => Setting::TYPE_IMAGE,
        ]);

        $this->actingAs($this->writer())
            ->delete(route('admin.setting.destroy', $setting->id))
            ->assertRedirect(route('admin.setting.list'));

        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
        // Nothing was removed from the disk: the path was never ours to delete.
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_deleting_a_setting_removes_its_uploaded_image(): void
    {
        Storage::fake('public');
        $writer = $this->writer();

        $this->actingAs($writer)->post(route('admin.setting.store'), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('logo.png'),
        ]);

        $setting = Setting::where('slug', 'deilar_logo')->firstOrFail();
        $path = $setting->value;

        $this->actingAs($writer)->delete(route('admin.setting.destroy', $setting->id));

        $this->assertDatabaseMissing('settings', ['id' => $setting->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull(SiteSettings::get('deilar_logo'));
    }

    public function test_switching_a_row_away_from_image_clears_the_orphaned_file(): void
    {
        Storage::fake('public');
        $writer = $this->writer();

        $this->actingAs($writer)->post(route('admin.setting.store'), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value_type' => Setting::TYPE_IMAGE,
            'value_image' => UploadedFile::fake()->image('logo.png'),
        ]);

        $setting = Setting::where('slug', 'deilar_logo')->firstOrFail();
        $path = $setting->value;

        $this->actingAs($writer)->put(route('admin.setting.update', $setting->id), [
            'slug' => 'deilar_logo',
            'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
            'value' => 'https://deilar.com/logo.png',
            'value_type' => Setting::TYPE_URL,
        ]);

        Storage::disk('public')->assertMissing($path);
        $this->assertSame('https://deilar.com/logo.png', $setting->fresh()->value);
    }
}
