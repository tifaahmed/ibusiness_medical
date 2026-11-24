# مفتاح منفصل لتحويل نقاط الولاء وتحويل المحفظة
## المطلوب (حرفياً، الرسالة كانت مكتوبة معكوسة)
"انا عايز موضوع مشاركه نقاط الولاء ليه مفتاح مختلف عن مشاركه المحفظه كل واحد ليه مكان خاص بسه لو ده مش معمول اعمل التعديل وطبقه على التطبيق والموقع"
## القرارات
- كل نوع (points / wallet) له مفتاحه المستقل: loyalty_transfers_points_enabled / loyalty_transfers_wallet_enabled. المفتاح 49 (loyalty_transfers_enabled) يبقى قديم (legacy) ومش بيتحكم.
- migration تحافظ على الوضع الفعلي الحالي (القيمة = 49 AND own) عشان ما يتفتحش حاجة بالغلط.
## PROGRESS
- [x] MemberTransfers: kindEnabled = مفتاحه بس، enabled() = أي نوع شغال
- [x] migration 2026_11_23_110000 (اتشغلت محلياً بعد backup للـ settings في scratchpad)
- [x] صفحة /admin/loyalty تعرض المفتاحين
- [x] التطبيق والموقع: مفيش تعديل (بيقرأوا enabled + kinds أصلاً)
- [ ] نشر: mysqldump ثم migrate --force ثم vite build --minify esbuild
