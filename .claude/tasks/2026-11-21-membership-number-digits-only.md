# رقم العضوية أرقام فقط (2026-11-21)

المطلوب (حرفياً): "فى الاعضاء خلى ان رقم العضويه بيقبل ارقام فقط"

## القرارات
- الفورم يشيل أي حرف مش رقم أثناء الكتابة + inputmode numeric.
- الـ zod (memberValidation.js) و UpdateMembershipRequest يرفضوا غير الأرقام (الرقم القديم غير المتغير يفضل قابل للحفظ).
- Store شغال أصلاً digits:6.

## PROGRESS
- [x] MemberForm.vue: تنظيف المدخل
- [x] memberValidation.js regex
- [x] UpdateMembershipRequest regex (لو الرقم اتغير فقط)
- [ ] php -l + vite build (npx vite build --minify esbuild)
