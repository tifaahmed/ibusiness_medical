# Quick add -> separate page; sales locked from linked admin; sales picker on admin form

## المطلوب (حرفياً)
انا عايز انجزئيه ال Quick add فى admin/user/membership تكون بتفتح فى صفحه منفصله من فى بىب اب
ولو الادمن مرتبط بحساب سالس اسم السالس يكون السالس موجود فى الفورمه ومتحدد ومش قابل للتعديل وبيتم حفظه مع الفورمه وبعد الحفظ بيفتح صفحه التعديل للعضو الى تم انشاءاه
وحط فى صفحه انشاء وتعديل الادمن خانه تختار السالس المرتبط بس هوا خانه اختيارى

## القرارات
- Builds on sales.admin_user_id (one-to-one, see 2026-11-04-sales-admin-link.md).

## PROGRESS
- [x] explored; admin form sales picker done (Sales::linkAdmin, linkableOptions)
- [x] quick add page (QuickAdd/QuickAddView.vue), route admin.user.membership.quick-add.page, sales lock server-side, redirect to member edit; dialog deleted
- [x] vite build; tests need THROWAWAY db only; migration not run

## 2026-11-04 — طلب إضافي (حرفياً)
لو حصل ان رقم الهاتف موجود بالفعل خليه مينفعش ينضاف فى الاضافه السريعه للاعضاء مع السبب
- [x] رسالة واضحة بصاحب الرقم في QuickAddMemberController::store (+ اختبار) ; test_member_update (MemberUpdateUncheckedTest) لسه شغال

## 2026-11-04 — طلب إضافي (حرفياً)
انا عايز الرساله الى هتنبعت تكون ظاهره طول الوقت بس فاضيه من البياانات وتتعدل وتتغير وانا بملى الخانات
- [x] معاينة الرسالة ظاهرة دايماً في QuickAddView.vue (قالب فاضي بـ placeholders ويتملى مع الاختيارات)

## 2026-11-04 — طلب إضافي (حرفياً)
admin/user/membership/quick-add  حط فلتر جوا القائمه السالس
- [x] قايمة السالس في QuickAddView.vue تبقى قابلة للبحث (SearchableSelect) بدل select عادي

## 2026-11-04 — طلب إضافي (حرفياً)
admin/user/membership/quick-add   حط ده سواء شركات او افراد
أهلاً بك في ديلر! لديك تجربة مجانية
- [x] افتتاحية الرسالة موحدة للشركات والأفراد في QuickAdd::message ; ذيل كل نوع يفضل زي ما هو

## 2026-11-04 — طلبات إضافية (حرفياً)
admin/user/membership/quick-add انا حطيت رقم تليفون موجود بالفعل ومظهر مشكله
انا عايز بمجرد ما اكتب ال 11 رقم يتاكد هوا موجود ولا
زعايز عداد على عدد الحروف عشان اعرف انا تخطيت حجم الرساله الواحد ولا
---
admin/user/membership/quick-add  خلى خيار الاندرويد والشركات هوا الخيار الى محدد فى البدايه
وياريت لو تخلى الفورمه سهله على الموبايل
- [x] phone-check endpoint (quick-add.phone-check) + live check at 11 digits ; counter N/70 + bar, warn when over ; defaults android+company ; mobile layout (h-12 controls, 2-col toggles, fixed bottom action bar)
- [x] verified: QuickAddMemberTest 11 passed (72 assertions) + vite build OK (2026-11-04)
