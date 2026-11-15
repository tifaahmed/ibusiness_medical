# Website checkout: pay with loyalty points / wallet (opt-in toggles)

## المطلوب (حرفياً)
"الشراء ممكن يتم من الموقع او التطبيق انت متاكد الاتنين مستعدين لده انه يتم الدفع من النقاط او المحفظه وده مش بكون اتوماتيك لما يختارو تطبيق خصم النقاط او تطبيق خصك المحفظه ده يطبق على السعر" ... ثم "اعمل الكلام ده"
(Backend + app already ready; website had NO support.)

## القرارات
- Backend (../ibusiness_medical) owns rules. Website relays: GET /loyalty + /wallet with member token, sends use_points/use_wallet (default false), draws points_amount/wallet_amount/amount_due.
- Toggles only for signed-in members, OFF by default. Cart preview mirrors backend rule (PointsPayments::eligible/quote), never authoritative.

## PROGRESS
- [x] read checkout / cart / PlaceOrder / MemberSession / order-show
- [x] relay (FetchMemberSpendable + guest product points flags + partner order points_rate)
- [x] toggles + price preview (checkout.tsx)
- [x] PlaceOrderRequest sends flags; order-show draws result
- [x] CheckoutPointsWalletTest (3 pass). NOT RUN: npm build, browser check, backend sqlite tests (schema build broken)
