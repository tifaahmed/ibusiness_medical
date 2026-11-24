# Push notifications to a chosen group
## المطلوب (حرفياً)
عايز لما اجى ارسل اشعاراتت لمستخدمى التطبيق انى اقدر ابعت لمجموعه معينه مش كل الناس كل مره

## القرارات
audience جديد `group` + عمود JSON `audience_filters` (migration إضافية فقط) + `App\Support\PushAudience` (مصدر واحد للعدّاد والإرسال).
## PROGRESS
- [x] قراءة الكود
- [x] migration 2026_11_23_100000 + PushAudience + controller (count) + job + route + lang + Vue
- [x] SQL متحقق منه بـ toSql
- [ ] المستخدم يشغّل: php artisan migrate --force (بعد mysqldump) ثم npx vite build --minify esbuild
- [ ] اختبار يدوي في /admin/push بأجهزة حقيقية
