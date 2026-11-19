# Customer can hide an untouched order; admin still sees it flagged
## المطلوب (حرفياً)
فى حاله ان المنتج لسه كله لا اندفع ولا اتوافق عليه ولا اتسلم ولا اى حاجه عادي اقدر انى ان صاحب الطلب يخفيه عشان هوا مش موافق عليه بعد تاكيد منه وعايز الادمن يكون شايف ان الطلب ده العميل خفاه من عنده لانه مش عايزه
## القرارات
- NEVER delete: orders.hidden_by_customer_at (add-only). Hidden = excluded from the buyer's own lists only; admin sees everything with a "hidden by customer" badge + filter + log + bell notification.
- Eligible only while untouched: payment pending, delivery pending, nothing paid (total_paid/wallet/points = 0), no receipt, not shipped, not already hidden. Needs explicit confirm. Backend decides (can_hide), clients draw.
## PROGRESS
- [~] backend
- [x] backend DONE+verified in tx: migration 2026_11_19_140000 RUN (backup pre-hidden-orders-*.sql), Order::canBeHiddenByBuyer, POST /mobile|partner/orders/{code}/hide (confirm required), resource can_hide/hidden_by_buyer, member list excludes hidden, OrderLog hidden_by_customer, bell notification order_hidden
- [~] app + website forks running; [ ] admin list chip/filter + show banner (me)
- [x] admin: list chip + `hidden` filter, show banner; AGENTS.md; app fork done; [~] website fork
