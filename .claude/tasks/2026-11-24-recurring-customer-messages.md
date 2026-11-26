# Customer messages: send every time period (recurring)

## المطلوب (حرفياً)
"انا عايز الرسايل الى بتتبعت للعملاء  اقدر احط  خيار انها تتبعت كل فتره زمنيه"

## توضيح 2026-11-26 (حرفياً)
"انا عايز ان رسايل الotp  اقدر انى اشغل يوم حدد كل اسبوع او كل شهر  لو مره فى تاريخ محدد"
ثم: "انا اقصد الاشعارات الى بتبعت للعملاء على الموبايل مش ال otp  من خلال التطبيق من firebase   مش otp"
=> المطلوب: إشعارات Firebase (صفحة /admin/push) تتجدول: يوم كل أسبوع / يوم كل شهر / مرة في تاريخ محدد.

## PROGRESS
- [x] فحص نظام الإشعارات (push) وإزاي بيتبعت
- [x] migration 2026_11_26_120000 + PushSchedule + أمر push:send-scheduled (كل دقيقة) + controller + routes + lang + Index.vue
- [x] حساب المواعيد متحقق منه (tinker من غير DB: 8/8)، pint، npx vite build --minify esbuild نجح
- [x] AGENTS.md
- [~] tests/Feature/Admin/PushScheduleTest.php مكتوب ولسه ما اتشغلش: auto mode رفض التشغيل (RefreshDatabase). محتاج إذن المستخدم، على ibusiness_phpunit_test بس.
- [x] migrate محلياً — اتعمل 2026-11-26 (5 migrations)؛ الصفوف قبل/بعد متطابقة (settings +3)
- [ ] تجربة الصفحة في المتصفح (موبايل/ديسكتوب/RTL)
- [ ] نشر: backup + `php artisan migrate --force` + build + cron `* * * * * cd /path && php artisan schedule:run`

## تشغيل التست
نسخة من phpunit.xml فيها DB_DATABASE=ibusiness_phpunit_test ثم:
`php vendor/bin/phpunit -c <النسخة> tests/Feature/Admin/PushScheduleTest.php`

## رد المستخدم 2026-11-26
"4- اعمل الميجرات عشات يشتغل كله صح"
- [x] backup: ~/db-backups/ibusiness_medical_card_20261115-20261126-125335-before-push-schedules.sql (+sha256, 106 جدول) ثم migrate؛ controller بيرجع بيانات الصفحة؛ push:send-scheduled --dry شغال
- [ ] محلياً الإرسال المجدول محتاج `php artisan schedule:work` (ما اتشغلش: بيشغل كمان باقي المهام المجدولة)
