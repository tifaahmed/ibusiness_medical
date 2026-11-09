# نص رسالة OTP قصير + تحكم من لوحة التحكم

## المطلوب (حرفياً)
"انا عايز رساله الotp تكون your code is 1223 عشان تكون قصيره عشان المشاكل وحطلى تحكم فى الجمله فى لوحه التحكم. الى موجود فييه كلمه ديلر وكلمه verification وده انا مش عايزه"

## القرارات
- الإعداد otp_message موجود بالفعل في OtpSettings بس من غير شاشة. الافتراضي يبقى "Your code is :code" (من غير :app ولا verification).
- حقل النص يتحط في MemberOtpDialog (الإعدادات العامة) + AdminMembershipOtpController::site.
- بدون migration/حذف بيانات؛ لو فيه صف otp_message قديم في الداتابيز بالنص القديم بيتعدّل من الشاشة.

## PROGRESS
- [x] OtpSettings default + controller + policy prop + dialog
- [x] تست OtpSmsTextTest + pint
- [ ] نشر: build للوحة الأدمن (+ مراجعة نص قديم في settings على السيرفر)
