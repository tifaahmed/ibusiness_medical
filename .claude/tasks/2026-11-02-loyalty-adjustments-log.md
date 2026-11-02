# Who gave/took loyalty points & wallet — member tabs + sidebar log page

## المطلوب (حرفياً)
"انا عايز سواء نقاط ولاء او محفظه الى اتحط يتوضح انه اتحطعن طريق مين واعملى  مكان لوحده فى القائمه على الناف بار ادوس عليه اشوف كل الناس انى اعطت نقاط ولاء او سحبتها من اى عضو  ومين العضو ورقمه والسبب"

## PROGRESS
- [~] قراءة sources + sidebar
- [x] LoyaltyAdjustmentLogController (union points+wallet حيث created_by not null) + route admin.loyalty.log + Log.vue + sidebar link (Points & wallet log) + فلاتر (بحث/نوع/اتجاه/أدمن/تاريخ) + totals + صفحات
- [x] تابات العضو: كل سطر بيعرض "By: اسم الأدمن" (أو "the member / system")
- [x] LoyaltyAdjustmentLogTest نجح على membership_test؛ vite build تمام
- [ ] لم يُختبر في المتصفح (موبايل/تابلت/ديسكتوب + RTL)
- [x] (2026-11-02 لاحقاً) المستخدم: 'مش عايز /admin/loyalty، عايز صفحة منفصلة' → نقلت الجدول لـ /admin/points-wallet-log (route admin.points-wallet-log) خارج مجموعة admin/loyalty؛ السؤال المفتوح: هل يقصد كمان إخفاء/حذف قسم الإعطاء من /admin/loyalty؟

## 2026-11-02 لاحقاً
المطلوب: "http://127.0.0.1:8004/admin/points-wallet-log  حسن الوات التاجات الى موجوده عشان اعرف اقراها" (تحسين ألوان التاجات/الشارات لتتقرا)
- [~] ألوان solid عالية التباين في Log.vue + تابات العضو
- [x] ألوان solid في Log.vue + تابات العضو
## قانون موحد للتاجات (حرفياً)
"حط قوانين موحده فى الموقع كله عشان الوان التاجات لازم تكون بالوان موحده وتكون مناسبه فى لوحه التحكم — ده قانون مهم لكلود يلتزم فيه"
- [~] كلاسات .tag-* في app.css + مكوّن Tag.vue + قسم في AGENTS.md + memory + تطبيقها على الصفحات الجديدة
