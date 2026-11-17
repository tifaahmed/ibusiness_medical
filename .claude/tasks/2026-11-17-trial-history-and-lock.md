# Trial lock after end date + trial history (per member)

## المطلوب (بكلام المستخدم حرفياً)
"مجرد ما تنتهى تاريخ التجربه يفترض زرار التجربه  يتقفل

ويفترض نه يكون ظاهر ان العضو ده التجربه كانتكام يوم منن كام لكام فى حته على جنب عشان  لو مدينا فتره التجربه ومعاها مدينا فتره الانتهاء وخلصت يكون واضح ان العميل اتمد فى تجربته مرتين او تلت مرات الكلام دهيكون فى جدول منفضل ولكن ظاهر عند العميل فى التعديل او المشاهده ممكن فى تابه منفصله او بوب اب بحيث اعرف تاريخ العميل مع التجارب"

## القيود
- AGENTS.md: إضافة فقط (جدول جديد append-only، migration)، mysqldump قبل migrate، لا حذف، الاختبار على DB `_test` فقط.
- السابق: 2026-11-17-trial-end-date.md (trial_ends_at، isTrial مشتق).

## PROGRESS
- [x] جدول `membership_trials` (migration 2026_11_17_120000، add-only) + model + `TrialHistory` hook في Membership::saved
- [x] mysqldump قبل: ~/db-backups/ibusiness_medical_card_20261115_before_trial_history_*.sql (93MB، متحقق) → migrate → backfill 113 صف `started`، إعادة التشغيل = 0
- [x] فحص الـ hook بـ transaction + rollback: extended / ended / lock شغالين، الصفوف رجعت 113
- [x] القفل: `trialLocked()`، فورم (checkbox + التاريخ disabled + ملاحظة)، UpdateMembershipRequest يرفض تغيير is_trial، action ما يكتبش التاريخ
- [x] بوب أب `TrialHistoryDialog.vue` + route `admin.user.membership.trials` + زرار في MemberForm و Show.vue
- [x] AGENTS.md بند 24، لغة ar/en
- [ ] اختبار `TrialHistoryTest` + `MemberKindPlansTest` لم يتشغلوا (محتاجين MySQL `_test`)
- [ ] لم يتم فحص الواجهة بالمتصفح (البوب أب، القفل، RTL، عرض الموبايل)
- [ ] لم يتم commit
