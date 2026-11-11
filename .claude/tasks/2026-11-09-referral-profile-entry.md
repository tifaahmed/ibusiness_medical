# Referral code entry from the profile (once, forever) + 1 EGP to referrer

## المطلوب (حرفياً)
"انا عايز فى البروفايل الشخصي اقدر لو انا معملتش رفيرال كود انى اقدر ادخل الكود ولكن الادخال مره واحده للابد وقبل ما انفز بيجيلي تاكيد بالرقم والعضو التابع للرقم ده لو موافق بيتبعت الفلوس لمحفظه الشخص الى عمل التوجيه ليك ولما بتدفع الاشتراك بعدها الفلوس بتروح لصاحب الرفيرال كود
الترتيب لما هدخل الرفيرال كود ويتقبل صاحب الرفيرال هياخد 1 جنيه وده انا متحكم فيه من لوحه الاعددادات من الباكاند
ولما بحاول ادفع الاشتراك انا بكون شايف الخصم وبحصل عليه لما بشترك
ولما حطيت الرفيرال شايف انى نجحت وحاخد الخصم واظهر الخصم الى هحصل عليه ولينكات الاشتراك تظهر بالخصم للعميل
للعلم انا مينفعش احط رفيرال كود بعد ما انا اشتركت بالفعل
الرفيرال كود شغال طول الوقت لاى حد مفيش معاد انتهاء ليه
حتى لو انا عامل حساب من سنه ادام انا مشتركتش اقدر عادى احط رفيرال كود عشان اخد خصم على العضويه
الى يقدر يحط الرفيرال كود فقط لازم اكون افراد مش شركات"

## القرارات
- Backend owns rules (ibusiness_medical); app + website only relay/draw. Update contract item 18 in all 3 AGENTS.md/CLAUDE.md.
- too_late/window REMOVED (no expiry). Company members refused (new reason `company`). Paid already => refused (exists).
- Referrer's reward (`referral_signup_bonus`, admin setting; user wants 1 EGP -> set in /admin/setting) paid at CLAIM time (accepted), not at trial start.
- New preview endpoint (no write) returns referrer card number + name for confirm step.

## PROGRESS
- [ ] read Referrals::claim / ReferralController / clients

## طلب إضافي (حرفياً)
"انا عايز الادمن يقدر بردو يحط رفيرال كود لاى حد من عنده وفى الحاله ديه صاحب الرفيرال هياخد فلوسه وكان الراجل سجل عن طريقه الى هوا 1 جنيه تبع الاضافه لجديده بس طبعا بعد التاكيد على الكلام ده"
= الأدمن يحط رفيرال لأي عضو (preview/تأكيد الأول) والـ referrer ياخد مكافأة التسجيل (referral_signup_bonus) — نفس قواعد الفرد/ما دفعش/مرة واحدة، ومسجل باسم الأدمن.

## PROGRESS (حتى الآن)
- [x] Backend: Referrals::check() (بدون كتابة) + claim بلا انتهاء + رفض الشركات + المكافأة عند القبول؛ ReferralController::preview + route me.referral.preview
- [x] tests/Feature/ReferralProfileEntryTest.php مكتوب، لم يُشغَّل (sqlite مكسور بالـ migrations؛ محتاج DB مؤقتة *_test)
- [x] admin endpoint (MemberReferralEntryController) + MemberReferralCard.vue في تاب المحفظة؛ test passed
- [x] app (ReferralCodeEntry) + website (/account panel) عبر agents؛ tsc نظيف، tests الخاصة بهم نجحت؛ لم يُجرَّب في متصفح
- [x] عقد البند 18 محدّث في 3 مشاريع

## طلب إضافي (حرفياً)
"للعلم نفس الكلام ده لازم يطبق على الناس الى مسجله من الموقع ديلر نفس كل الاجرائات والصلاحيات الى يتعمل فى التطبيق يتعمل فى الموقع ~/projects/deilar/deilar"
= الموقع (deilar) لازم يعمل نفس الشيء: خانة إدخال الكود في البروفايل + تأكيد + إظهار الخصم، بنفس قواعد الباك.

## PROGRESS تحديث
- [x] Backend tests: ReferralProfileEntryTest + ReferralTest (20 passed على DB مؤقتة ibusiness_medical_scratch_test، env: APP_CONFIG_CACHE=/tmp/nocache.php DB_DATABASE=ibusiness_medical_scratch_test APP_ENV=testing)
- [x] تحديث ReferralTest للعقد الجديد (المكافأة عند القبول)
