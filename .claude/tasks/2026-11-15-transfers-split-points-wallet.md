# Separate switches: transfer points vs transfer wallet
## المطلوب (حرفياً)
"السماح للأعضاء بتحويل النقاط / رصيد المحفظة لبعضهم (بموافقة المستلم) — عايز افرق بين تحويل النقاط وتحويل المحفظه"
## القرارات
- الـ master loyalty_transfers_enabled يفضل. + مفتاحين: loyalty_transfers_points_enabled / loyalty_transfers_wallet_enabled (افتراضي ON، لازم الـ master شغال).
- المنع على الطلبات الجديدة بس (reason: kind_disabled). القبول/الإلغاء لطلب قديم مش بيتأثر.
- GET /transfers يرجّع kinds {points,wallet}; التطبيق يعرض النوع المفعّل بس.
## PROGRESS
- [x] backend MemberTransfers + migration + controller
- [x] test
- [x] app TransfersPanel + CLAUDE.md contract
