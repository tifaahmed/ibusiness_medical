# Role-level monthly limits for add/take points & wallet

## المطلوب (حرفياً)
"http://127.0.0.1:8004/admin/roles/6/edit  انا هنا اخدت صلاحيه انى اخد او ادى فلوس ومفرضتش عليا رقم محدد كا حد اقصي ليه ده حصل"

## السبب (محقق من الكود)
الحدود كانت per-admin بس (صفحة تعديل الأدمن)، ومفيش صف = غير محدود، والسوبر أدمن مش محدود أبداً. صفحة الدور مفيهاش أي حد.

## القرار
حد شهري على مستوى الدور (جدول role_ledger_limits) = الافتراضي لكل حامليه؛ صف الأدمن بيعلو عليه (inherit | unlimited | رقم). الدور بأكتر من حد → الأضيق. صفحة الدور بتوضح صراحة "غير محدود (لم يُحدد)" بتحذير.
## PROGRESS
- [~] قراءة Role edit controller/view
- [x] migration 2026_11_02_150000_create_role_ledger_limits (اتشغلت محلياً)؛ RoleLedgerLimit؛ AdminLedgerLimits::limitOf/sourceOf (admin row يعلو > الأضيق من الأدوار > غير محدود)
- [x] RoleLedgerLimitsCard في تعديل الدور (تحذير "غير محدد = غير محدود")؛ كارت الأدمن بقى inherit/unlimited/limit؛ AGENTS.md
- [x] اختبارات LedgerPermissionsLimitsTest (9) + DashboardBlockTest (5) = 14 نجحوا؛ vite build تمام؛ لم يُختبر في المتصفح
- [ ] على السيرفر: mysqldump ثم migrate --force (3 migrations اليوم: 130000, 140000, 150000)
