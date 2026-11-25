# نص رسالة OTP: Your code from iBusiness is <code>

## المطلوب (حرفياً)
"the otp change the crunt message to 'Your code from iBusiness is '"

## القرارات
- النص الجديد: `Your code from iBusiness is :code`.
- migration بتغيّر صف settings `otp_message` بس لو لسه على الافتراضي القديم `Your code is :code` (أي نص كتبه الأدمن بنفسه ما يتلمسش). down() ما بتعملش حاجة.
- القيمة القديمة محلياً (للرجوع): `Your code is :code` (settings id 98).

## PROGRESS
- [x] الكود + migration + التست + تحديث الصف المحلي
- [ ] نشر: `php artisan migrate --force` (بعد backup) + build للوحة الأدمن
