<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\OtpSettings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * The site details every install starts with — the ones that used to live
     * in .env as DEILAR_*. They are seeded, not hardcoded: an administrator is
     * expected to edit them, and adding a detail later means adding a row here
     * (or in the admin) rather than touching a schema.
     *
     * Existing rows are left alone. Re-running the seeder on a live install
     * must not overwrite a phone number someone has since corrected.
     */
    public function run(): void
    {
        $settings = [
            [
                'slug' => 'deilar_name',
                'name' => ['en' => 'Site name', 'ar' => 'اسم الموقع'],
                'value' => 'Deilar',
                'value_type' => Setting::TYPE_STRING,
            ],
            [
                'slug' => 'deilar_url',
                'name' => ['en' => 'Site URL', 'ar' => 'رابط الموقع'],
                'value' => 'http://localhost:8001',
                'value_type' => Setting::TYPE_URL,
            ],
            [
                'slug' => 'deilar_phone',
                'name' => ['en' => 'Phone number', 'ar' => 'رقم الهاتف'],
                'value' => '01020709993',
                'value_type' => Setting::TYPE_PHONE,
            ],
            [
                'slug' => 'deilar_email',
                'name' => ['en' => 'Email address', 'ar' => 'البريد الإلكتروني'],
                'value' => 'info@deilar.com',
                'value_type' => Setting::TYPE_EMAIL,
            ],
            [
                'slug' => 'deilar_address',
                'name' => ['en' => 'Address', 'ar' => 'العنوان'],
                'value' => '44 Abdel Moneim Riad St., off the Nile Corniche, El Warraq, Giza — first floor',
                'value_type' => Setting::TYPE_TEXT,
            ],
            [
                // A file path, not a link: it resolves through Setting::url(),
                // which serves an uploaded file off the public disk and falls
                // back to the copy shipped under public/.
                'slug' => 'deilar_logo',
                'name' => ['en' => 'Logo', 'ar' => 'الشعار'],
                'value' => 'images/logo/dielar.png',
                'value_type' => Setting::TYPE_IMAGE,
            ],
            [
                // What the printed QR codes encode — the public site, which is
                // not necessarily the same host the application runs on.
                'slug' => 'deilar_qrcode',
                'name' => ['en' => 'QR code link', 'ar' => 'رابط رمز الاستجابة'],
                'value' => 'https://deilar.com',
                'value_type' => Setting::TYPE_URL,
            ],
        ];

        /*
         * How the storefront's phone login behaves. Seeded rather than
         * hardcoded for the same reason the details above are — an
         * administrator turns SMS off and works with the fixed code without a
         * deploy — and listed by `OtpSettings` rather than here so the defaults
         * that class reads and the rows an install starts with cannot drift.
         */
        $settings = [...$settings, ...OtpSettings::seedRows()];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(
                ['slug' => $setting['slug']],
                $setting
            );
        }
    }
}
