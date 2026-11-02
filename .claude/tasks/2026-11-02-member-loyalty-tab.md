# Loyalty points tab on member edit page

## المطلوب (حرفياً)
"admin/user/membership/fryal-saayd-mhmd/edit  لو حبيت اضيف ليه نقاط ولاء انا  عايز تاب جديد اتفرج على الموجود واعرف ازود عليه او انقس منه وكل تذويد او نقس لازم يكون فى سطر جديد لوحده كا معامله عشان كل كميه يكون محفوظ زادت منين وازاى"

## PROGRESS
- [~] قراءة /admin/loyalty (grants + ledger endpoints) و MemberEditView tabs
- [x] MemberLoyaltyCard.vue + تاب "loyalty" في MemberEditView؛ يستخدم endpoints الموجودة (ledger GET + grants POST: سطر ledger جديد لكل زيادة/نقص، note إجباري، created_by). الرصيد بعد كل سطر محسوب من الرصيد الحالي
- [x] vite build
- [ ] لم يُختبر في المتصفح
