# Order edit page: show clearly what was paid by points / wallet / real money

## المطلوب (حرفياً)
"/admin/order/DL-UE4HGMMX/edit  مش واضح ان اندفع نقاط ولاء ولا فلوس ومش واضح كم نقطه وكم كان سعر الجنيه للنقطه الولاء"

## القرارات
- الصفحة: resources/js/Pages/Admin/Order/Edit/OrderEditView.vue (+ _components/Form) و AdminOrderEditResource.
- Show page already has real_paid / wallet_amount / points_amount (uncommitted). Edit page has none.
- Rate: orders don't archive points-per-EGP; derive points_used / points_amount (exact rate at sale), fall back to LoyaltyPoints::pointsPerEgp().
- Tags use chip-* classes from status.css only. Additive only, no migration.

## PROGRESS
- [x] found edit page + resource
- [x] add fields to AdminOrderEditResource
- [x] draw a "How it was paid" block in the edit form (real money / wallet / points: N points = X EGP, rate N points per 1 EGP)
- [x] en/ar lang keys
- [ ] verify in browser on DL-UE4HGMMX

## تعديل 2 — المستخدم: "وقت الشراء كان 200 ب 1 جنيه وبعد الطلب اصبح 1000 نقطه ب 1 جنيه. فى الطلب لازم اشوف وقت الطلب كان كام جنيه لكم نقطه واندفع كام نقطه"
- [x] migration 2026_11_14_100000 adds nullable orders.points_rate (+ backfill of old orders from points_used/points_amount, fills nulls only)
- [x] PointsPayments::applyToOrder archives the rate at sale; Order fillable/cast; show+edit resources read the column (fallback derived)
- [x] show page: chips real/wallet/points + "rate at sale" line; edit page same block
- [ ] NOT RUN: migration (deploy: mysqldump, then `php artisan migrate --force`); build; browser check on DL-UE4HGMMX
