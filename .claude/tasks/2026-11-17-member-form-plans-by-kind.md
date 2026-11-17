# Member form: plans by kind (company vs individual) + ibusiness notice

## المطلوب (بكلام المستخدم حرفياً)
1. "عايز ااكد ان فى التعديل والانشاء للاعضاء لو انت شركات الاشتراك فقط شهرى لكن لو انت افراد تقدر تحدد شهرى او نصف سنوى او شهرى" (الأخيرة غالباً سنوى)
2. "عايو اظهر اشعار واضح ان لو انت شركات اظهر كلام يوضح ان لازم الرقم ده يكون فى ibusniss" — لما العضو شركة يظهر تنبيه واضح إن الرقم لازم يكون في ibusiness.

## القيود
- لا حذف بيانات، تغيير إضافي فقط (AGENTS.md NEVER LOSE DATA).
- التنبيه يستخدم panel-note من status.css، مش ألوان Tailwind.
- الخطط من الباك إند (`MembershipPlan::plans($kind)`)، الواجهة ما تحسبش.

## قرارات المستخدم (2026-11-17)
- الرقم في التنبيه = **رقم التليفون** (الشركة: لازم يكون موجود في ibusiness).
- قاعدة "الشركة شهري فقط" تتطبق على: الأدمن + الباك إند (`MembershipPlan::plans`) + التطبيق + الموقع (الاتنين بيرسموا `status.plans` من الباك إند). الأفراد: شهري / نصف سنوي / سنوي.
- التخزين: `memberships.payment_type` (string) تتوسع بقيمة `half_yearly` (إضافة فقط، مفيش migration). سعر الشركة السنوي يفضل في الإعدادات (مش بيتمسح) بس مش بيتباع.
- ربط "monthly يتطلب company/sales" يتطبق على الشركات بس، مش الأفراد.

## PROGRESS
- [x] فحص الكود (plans() كانت بتبيع للشركة سنوي+شهري؛ الفورم مكانش فيه اختيار خطة — payment_type مخفي بـ v-if=false)
- [x] enum `half_yearly` + lang (ar/en)
- [x] `MembershipPlan::plans('company')` = شهري فقط
- [x] Store/UpdateMembershipRequest: الشركة المدفوعة = monthly فقط؛ company/sales مطلوبين للشركة بس
- [x] Store/Update actions: الشركة تتحفظ monthly
- [x] MemberForm.vue: اختيار الخطة حسب النوع + تنبيه ibusiness (panel-note) + memberValidation.js
- [x] AGENTS.md بند 22
- [ ] لم يتم تشغيل `tests/Feature/MemberKindPlansTest.php` (مفيش DB تجريبي مثبت إنها مش الـ live؛ sqlite مكسور) — يتشغل على MySQL `_test`
- [ ] لم يتم النظر في الواجهة بالمتصفح (التنبيه + الخيارات) بعد
- [ ] لم يتم commit
