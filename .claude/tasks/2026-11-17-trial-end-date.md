# Trial end date on members (create/edit) + backfill old company data

## المطلوب (بكلام المستخدم حرفياً)
"انا عايز فى الاعضاء لو انشاء او تعديل المهم فى زرار انه تحديد انه تجريبيtrial  لازم ادخل جنبه تاريخ محدد لانهاء جمله ححساب تجريبي  ولازم التاريخ ده يكون يتساوي او اقل من تاريخ انتهاء العضويه  وطول الوقت لو الحساب ده مسجل تجريبي اظهر فى التطبيق او الموقع للعملاء لو انا مسجل دخول انى فى الفتره التجريبيه

وعايز للداتا القديمه اى عضو نوعه شركات عضويه اساسيه او عضوي اقارب تابعه لعضويه اساسيه شركات  لو معاد انتهاء العضويه بتاعه لو فتره الانتهاء الخاصه بيه اقل من شهر وتنتهى خط نفس الرقم ده وحطه كا تاريخ التجربه"

## القيود
- AGENTS.md: migrations تضيف فقط، نسخة mysqldump قبل أي تغيير داتا، كل كتابة re-runnable و`--dry`، الاختبار على DB `_test` فقط.
- المهمة السابقة (company شهري فقط) في 2026-11-17-member-form-plans-by-kind.md.

## قرارات المستخدم (2026-11-17)
- "اقل من شهر" = الباقي على انتهاء العضوية أقل من شهر من النهاردة (ولسه ما انتهتش).
- الداتا القديمة: تتكتب `trial_ends_at` = تاريخ انتهاء العضوية **وكمان plan = trial** للأساسي. القريب (plan=relative ثابت) ياخد التاريخ بس ويتبع كارت الأساسي.
- لو تاريخ التجربة عدّى والعضوية لسه شغالة = العضو دفع وبقى عضو عادي (مش تجريبي، مش أحمر). المشتق من التواريخ، من غير cron.
- `trial_ends_at` <= `expiration_date` إجباري (فورم + backend). مطلوب مع علامة trial.
- الباك فيل: command re-runnable بـ `--dry`، بعد mysqldump، وكل صف يتسجل قبله/بعده في member_logs.

## PROGRESS
- [x] migration `2026_11_17_100000_add_trial_ends_at_to_memberships` (عمود nullable، لسه ما اتشغلتش على أي DB)
- [x] Membership: trial_ends_at cast/fillable، isTrial()/trialPeriodOver/trialEndsOn/trialDaysLeft، scopes onTrial/notOnTrial (الفلاتر في القائمة)
- [x] Store/Update requests (مطلوب مع trial، <= expiration) + actions + edit resource
- [x] MemberForm.vue (حقل التاريخ بجنب Trial) + MemberStore.js + memberValidation.js + FormDateInput `max`
- [x] MembershipStatus: days_left للتجربة = لتاريخ التجربة، + trial_ends_on
- [x] command `members:backfill-company-trial` (مكتوب، لسه ما اتشغلش)
- [x] mysqldump: ~/db-backups/ibusiness_medical_card_20261115_before_trial_ends_20261117_124751.sql (93MB، متحقق منه) ثم migrate ثم --dry (10 كروت شركات) ثم تشغيل حقيقي: 10 كروت اتكتب لها التاريخ (كلها كانت plan=trial أصلاً)، إعادة التشغيل = 0
- [x] التطبيق والموقع بيقروا is_trial/days_left أصلاً (grep فقط، ما اتجربش بصرياً)
- [ ] اختبارات على DB `_test` (غير متاح الآن)
- [ ] التأكد إن التطبيق/الموقع بيعرضوا بانر التجربة لما `status.is_trial` (لم يُفحص)
- [ ] لم يتم commit
