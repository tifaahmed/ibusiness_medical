# Multi-store orders: per-store shipping, split display, invoices
## المطلوب (حرفياً)
حاليا لو العميل اشترا منتجات من متجرين يفترض سعر الشحن بتاع كل واحد يختلف وجوا الطلب والواحد يكون باين انه متقسم الطلبيه على كذا متجر واقدر احمل فاتوره كل واحد على حدا والاتنين مع بعض
## PROGRESS
- [~] explore: products.store_id, order_products, OrderController::store delivery calc, order admin show, invoice existing?
## القرارات / الخطة (2026-11-19)
- backend: migration ADD-ONLY: order_products.store_id + store_name (archive), table order_shipments (one row per store per order: subtotal, delivery cost/price/profit, free threshold/reason). orders.delivery_* = SUM of shipments (existing totals logic untouched).
- shipping per store = Store::shippingFor(governorate matched from customer_governorate text); store without price -> shop ShopDelivery figures (per store parcel). Free threshold per store vs that store's subtotal.
- invoices: no PDF lib installed -> printable HTML invoice (Save as PDF from browser): public GET /invoice/{order_code}/{store?} (order code is the credential). Order answers carry shipments[] + invoice urls.
- clients (website, app): show split + invoice links on order pages; cart quote endpoint so the cart shows the real per-store shipping.
## PROGRESS
- [~] backend migration + OrderShipments action + controller
- [ ] backend resources/admin order show + invoice view
- [ ] backend quote endpoint
- [ ] website, [ ] app
## طلب إضافي أثناء الشغل (حرفياً)
بس افتكر عايز احط ان ممكن احط ان اكتر من متجر الشحن بتاعهم واحد بيحث انهم بيتشاركو فى المخزن وعايز خيار فى اعدادات الموقع انها تخلى كل المنتجات على شحن واحد ولا  لا ونقدر نحدد رقم واحد للكل ولكل المحافظات عموما
- decisions: (1) shipping_groups table + stores.shipping_group_id: stores in one group (shared warehouse) ship as ONE parcel at the group's price/free-from. (2) site settings shop_single_shipping_enabled / _price / _free_from: when on, the whole order is ONE parcel at that one price for every governorate. Invoice unit = shipment (parcel); order_shipments.store_ids json lists its stores.
- [x] backend: migration 2026_11_19_110000 RUN (backup pre-order-shipments-*.sql): order_products.store_id/store_name, order_shipments(store_ids, shipping_group_id, price_source), shipping_groups, stores.shipping_group_id, settings shop_single_shipping_enabled/_price/_free_from. Models OrderShipment, ShippingGroup; App\Support\OrderShipping (plan/totals, verified in rolled-back tx: shop/store/group/single modes); Partner OrderController uses it (mobile shares it).
- [ ] NEXT: OrderResource shipments+invoices; invoice route+blade (/invoice/{code}/{shipment?}); admin store form shipping group; admin order show split; quote endpoint; website; app
- [x] OrderResource: items.store_id, shipments[], invoice_all_url; invoice route /invoice/{code}/{shipment?} + blade (tested 200/404)
- [ ] NEXT: admin store form shipping group; admin order show split + invoice buttons; quote endpoint; website; app
- [x] admin store form: shipping group select/new + group price/free-from (SaveStoreShippingGroup); admin order show: Shipments card + invoice links
- [ ] NEXT: quote endpoint (cart real per-store shipping), website, app, tests, vite build check
- [x] quote endpoints (partner + mobile), tests/Feature/OrderShippingTest.php 4 pass on ibusiness_phpunit_test, AGENTS.md section, e2e order in rolled-back tx OK
- [x] app fork done (tsc + 9 vitest pass); [~] website fork running
