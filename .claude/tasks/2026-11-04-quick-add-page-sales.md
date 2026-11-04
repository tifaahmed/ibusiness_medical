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
- [~] vite build; tests need THROWAWAY db only; migration not run

## 2026-11-04 — طلب إضافي (حرفياً)
لو حصل ان رقم الهاتف موجود بالفعل خليه مينفعش ينضاف فى الاضافه السريعه للاعضاء مع السبب
- [~] رسالة واضحة بصاحب الرقم في QuickAddMemberController::store (+ اختبار) ; test_member_update (MemberUpdateUncheckedTest) لسه شغال

## 2026-11-04 — طلب إضافي (حرفياً)
انا عايز الرساله الى هتنبعت تكون ظاهره طول الوقت بس فاضيه من البياانات وتتعدل وتتغير وانا بملى الخانات
- [~] معاينة الرسالة ظاهرة دايماً في QuickAddView.vue (قالب فاضي بـ placeholders ويتملى مع الاختيارات)
