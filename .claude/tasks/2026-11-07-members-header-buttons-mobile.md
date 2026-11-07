# Members list header buttons must all show on mobile

## المطلوب (حرفياً)
admin/user/membership  زرار الاضافه السريعه مش ظاهر فى حجم الموبايل لازم الزراير ديه تظهر كلها فى شكل الموبايل

## السبب
MemberListView.vue header card: flex-row + overflow-hidden, buttons container flex-shrink-0 no wrap -> Quick add clipped on mobile.

## PROGRESS
- [x] header wraps on mobile (flex-wrap; buttons row w-full sm:w-auto, flex-wrap)
- [ ] user to eyeball at mobile width (no browser check done; no vite build run)

## طلب إضافي (حرفياً)
نفس الكلام  طبقه مع كل الصفحات
- [x] 24 admin list headers (identical pattern) now flex-wrap + button row w-full sm:w-auto; Offer fixed too

## طلب جديد 2026-11-07 (حرفياً)
ده رابط التطبيق على الايفون https://apps.apple.com/sa/app/deilar/id6825271113?l=ar حطه فى المشروع
- [x] ios_store_url default set in config/mobile_app.php + MOBILE_IOS_STORE_URL in .env and .env.example (needs config:cache on cached hosts)

## طلب جديد 2026-11-07 — رابط مختصر (حرفياً)
انا عايز فعلا الدومان بتاعى وبعديه app  ونشيل موضوع انى بحدد ios&android  فى الانشاء السريع للعضو
لكن بعد الانشاء السريع للعضو انا ببعت فى الواتس الروابط كامله اندرويد وايفون  ورابط الموقع للعضويه حط فيها رقم العضويه 
زى ده https://deilar.com/membership/475248?slug=475248
- decisions: SMS carries short link https://deilar.com/app (redirect by device); WhatsApp keeps FULL android + iphone links + membership link with number
- [~] explore QuickAdd (device field, SMS template, WhatsApp links, card_link)
- explored: website ../deilar has ReferralLinkController (PLAY_URL, /r/{code}); backend QuickAdd::message(kind, device, wantsCard), QuickAddMember, QuickAddMemberController (device required), QuickAddAfterController summary card_link, QuickAddView.vue device buttons
- plan: [ ] website GET /app -> Play/App Store by UA (+ test)  [ ] backend config mobile_app.short_link default https://deilar.com/app  [ ] SMS uses short link, device removed from form/validation/preview/tests (quick_device column stays, null)  [ ] WhatsApp card_link = PublicMembershipUrl::qrForSlug(membership_number) (deilar.com/membership/N?slug=N); WhatsApp keeps full android+ios
- [x] DONE: website GET /app (ReferralLinkController::app, config services.deilar.play_url/app_store_url, tests/Feature/AppShortLinkTest.php 3 pass); backend config mobile_app.short_link (+MOBILE_SHORT_LINK in .env/.env.example); QuickAdd::message(kind, wantsCard, days) uses short link; device removed from form/validation/preview; WhatsApp card_link = qrForSlug(membership_number); QuickAdd tests 20 pass on ibusiness_phpunit_test
- deploy: website needs deploy (+config:cache), backend config:cache; no migration. vite build not run.
