# كود OTP رئيسي (master) يدخل جنب الكود الحقيقي

## المطلوب (حرفياً)
"خلى ان انا ممكن ادخل رقم 9999 فى مكان الotp وكل مره هكتب الكود ده هيوافق ويتخطى وطبعا خلى الرقم ده بقدر اتحكم فيه وحطه فى اعدادات النظام"
"الفكره ان انا لو جالى otp 5555 عادى جدا لو كتبت 5555 او 9999 الاتني هيدخلو"

## القرارات
- SMS يفضل شغال؛ الكود الحقيقي يدخل + الكود الرئيسي يدخل (خيار ب).
- قاعدة CLAUDE.md: للأدمن الأعلى بس، hash، سجل مراجعة، انتهاء اختياري، تحذير ظاهر، مفتاح تشغيل/إيقاف (الافتراضي مقفول).
- متعلقات: نسخة تسجيل في deilar/.claude/tasks/2026-11-09-otp-full-phone.md

## PROGRESS
- [x] قراءة MemberOtp + OtpSettings + الواجهة
- [x] كود: OtpMasterCode + MemberOtp::verify + controller master + route + migration + Vue dialog
- [x] تست MasterLoginCodeTest + 3 ملفات قديمة: 32 passed، pint نضيف
- [x] تحديث نص العقد (بند 20) في الـ 3 مشاريع
- [ ] نشر: php artisan migrate --force (بعد mysqldump) + npm run build للوحة الأدمن
