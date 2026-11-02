# Dedicated permissions + monthly limits for adding/taking loyalty points and wallet money

## المطلوب (حرفياً)
"انا عايز صلاحيات مخصصه لموضوع اضافه او حذف نقاط ولاء او محفظه

وعايز لو اديت الصلاحيه لحد اقدر احط حد استخدام شهرى او انى اخليها غير محدود"

## التصميم (قرارات)
- 4 صلاحيات standalone: `add loyalty points`, `take loyalty points`, `add wallet money`, `take wallet money` (مفيش auto-grant: افتراضي مرفوض؛ super_admin دايماً مسموح وغير محدود عبر Gate::before).
- حد شهري لكل (أدمن × صلاحية): جدول `admin_ledger_limits` (user_id, permission, monthly_limit nullable=غير محدود). مفيش صف = غير محدود. الاستهلاك = مجموع سطور الـ ledger اللي created_by=الأدمن في الشهر التقويمي (توقيت القاهرة) حسب الاتجاه (موجب=add، سالب=take) من كل المصادر اللي كتبها أدمن (grant/wallet/transfer/convert).
- التطبيق في QuizLoyaltyController (grant/adjustWallet/transfer/convertPoints) جوه transaction بقفل صف الأدمن؛ تخطي الحد = رفض برسالة واضحة. التحويل بين أعضاء بيحتاج take (المرسل) + add (المستلم) ويتحسب على الحدين.
- UI: قسم "الحدود الشهرية" في صفحة تعديل الأدمن (super admin فقط يعدّل) + التابات في صفحة العضو بتعرض المتبقي وتخفي الأزرار بدون صلاحية.
## PROGRESS
- [~] قراءة UserPermissionEnum/AdminUser forms
- [x] enum: 4 صلاحيات (standalone)؛ migration 2026_11_02_140000 (الصلاحيات للسوبر أدمن فقط + جدول admin_ledger_limits)؛ AdminLedgerLimits + LedgerLimitExceeded + AdminLedgerLimit model
- [x] QuizLoyaltyController: grant/adjustWallet/transfer/convert بيطبقوا الصلاحية + الحد (قفل صف الأدمن)؛ ledger بيرجّع allowance
- [x] UI: AdminLedgerLimitsCard في تعديل الأدمن (super فقط)، تابات العضو بتخفي الأزرار وتعرض المتبقي؛ vite build تمام
- [x] اختبارات (7 جداد + الـ 4 ملفات القديمة اتحدّثت وكلها نجحت؛ مجلد tests/Feature/Admin كله 407 نجح): tests/Feature/Admin/LedgerPermissionsLimitsTest.php (لسه ما اتشغلتش) + تحديث الاختبارات القديمة (tests/Feature/AdminWalletLedgerTest, AdminQuizLoyaltyTest, ...) اللي أدمنها بدون الصلاحيات الجديدة
- [ ] قرار مفتوح للمستخدم: الصلاحيات الجديدة متدّيش لحد غير السوبر أدمن (حتى اللي معاه manage memberships) — لازم تتدّي من الأدوار/الأدمن
- [x] migration اتشغلت محلياً بعد mysqldump (scratchpad/perm-backup-2-2026-11-02.sql)؛ AGENTS.md اتحدّث
- [ ] لم يُختبر في المتصفح؛ على السيرفر: mysqldump ثم migrate --force (2 migrations: 130000 points + 140000 ledger permissions)
