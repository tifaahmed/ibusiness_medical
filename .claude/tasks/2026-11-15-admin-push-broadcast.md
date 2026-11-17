# لوحة إرسال إشعارات للموبايل (للكل أو أعضاء محددين)
## المطلوب (حرفياً)
"انا عايز مكان فى الداشبورد اقدر منه ابعت اشعار لكل الناس او احدد ناس محددين يجيلهم الاشعار من الاعضاء"
## القرارات
- (يتحدد بعد فحص الموجود)
## PROGRESS
- [ ] فحص الموجود (PushTokenController، أي صفحة إشعارات في الأدمن)
## اللي اتلقى
- الصفحة موجودة من 11-14: /admin/push (permission `send push notifications`)، للكل أو عضو واحد بالتليفون.
## اللي اتعمل (11-15)
- [x] "أعضاء محددين": textarea بأرقام (سطر/فاصلة، لحد 500) — PushNotification::parsePhones، الكنترولر، الجوب، الـ Vue، lang en/ar
- [x] migration 2026_11_15_110000_widen_push_audience_phone (text)
- [x] tests/Unit/PushAudiencePhonesTest
- [ ] لسه: migrate على السيرفر (mysqldump الأول)، npm run build، ملف service-account، تجربة فعلية
- [x] زر "Copy app link" في قائمة المنتجات (ProductListTable.vue) ينسخ /products/<slug> للصقه في خانة Link بتاعة الإشعار
- [x] نفس الزر في قائمة المنشآت (FacilityListTable.vue) → /facilities/<slug>
- [ ] vite build لسه ماخلصش في الجلسة (timeout)؛ لازم npm run build على السيرفر/محلياً وتجربة الزرين
- [x] /admin/push: قايمة صفحات التطبيق الثابتة (APP_PAGES في Index.vue، مجمّعة) تملأ خانة Link. لو اتضافت صفحة جديدة في التطبيق (src/router.tsx) تتضاف هنا يدوي.
- [x] زر Copy app link في صفحة عرض المنتج (Product/Show.vue) كمان
# (مهمة جديدة) 2026-11-15 — أخطاء الشبكة عند تغيير الصفحات في الأدمن
المطلوب: "لما بغير الصفحات مش ظاهر اى تاثير لما بيكون فيه مشكله — هل فى طريقه لو ده حصل مشكله يتفتح الصفحه الجديد او نشوف اشعار ان فى مشكله" (Axios Network Error / ERR_FAILED على product و contact-messages)
- [x] app.js: router 'exception' → إشعار + إعادة فتح الصفحة (GET بس، مرة واحدة)، وإشعار على أي Network Error في axios (كل 20 ث بحد أقصى)
