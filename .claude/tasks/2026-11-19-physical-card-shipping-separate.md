# Physical card shipping price separate from product shipping
## المطلوب (حرفياً)
admin/setting  انا عايز التحكم هنا فى سعر الشحن للكارت الفيزيكال منفصل عن سعر الشحن للمنتجات
## PROGRESS
- [~] investigate how physical_card_shipping_price / shop_delivery_* are used and edited
- [x] finding: ALREADY separate. setting `physical_card_shipping_price` (id 28, =25) read by PhysicalCard::shipping() in PhysicalCardController; product shipping = shop_delivery_price/cost/free threshold (ShopDelivery). No code changed.
- [ ] open question to user: want a separate courier COST for physical cards too (profit tracking; today cost=0, profit=price)? or free-shipping threshold?
- [x] user confirmed: product shipping belongs to the shop, physical card shipping stays its own setting. Nothing more to do.
## طلب جديد (حرفياً)
مش شايف فلوس الشحص هنا ليه ؟ http://127.0.0.1:8004/admin/store/1/edit
- [~] investigate: store edit page lacks shipping money field
