# المهمة: عرض قيمة النقاط بالجنيه + تحويلها لمحفظة
## المطلوب (حرفياً)
"عايز اظهر للناس الى جانب النقاط هما جمعو كام جنيه بحيث انهم يقدرو يحولو النقاط الى جنيهات وتتحط على محفظه ليهم"
## القرارات
- الباك إند يملك الأرقام (loyalty_points_per_egp موجود)؛ التحويل: خصم نقاط من دفتر loyalty_transactions (append-only) + إيداع في دفتر محفظة؛ idempotent بمفتاح من العميل.
- مفتاح تشغيل إداري للتحويل (loyalty_wallet_enabled) وحد أدنى (loyalty_wallet_min_points)؛ العرض بالجنيه شغال دايماً.
- خارج النطاق (لم يُطلب): الصرف/السحب أو الدفع من المحفظة — أبلغ المستخدم.
## PROGRESS
- [x] فحص: مفيش محفظة مستخدم في الباك (كلمة wallet = طرق دفع فقط)
- [x] باك: wallet_transactions + LoyaltyWallet + POST /loyalty/convert + GET /wallet + إعدادات (مقفول افتراضياً) + 7 اختبارات
- [x] تطبيق: WalletPanel + قيمة النقاط بالجنيه + سطر المحفظة في السجل + اختبارات
- ⏳ php artisan migrate (المستخدم) ثم فتح loyalty_wallet_enabled من /admin/setting
