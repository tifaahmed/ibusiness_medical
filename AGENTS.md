# AGENTS.md

## Error Logging (required for all new features)

Every new feature must log errors on both sides to make debugging possible:

### Backend
- Wrap risky operations (payments, callbacks, DB writes) in try/catch.
- Use `Log::error()` / `Log::warning()` with context (route, payload summary, exception message + stack).
- Never swallow exceptions silently.

### Frontend (JS/Blade)
- Catch errors and report them via the existing endpoint: `POST /api/v1/client-errors`
  (`App\Http\Controllers\Api\V1\Guest\ClientErrorController` → `client_error_logs` table, viewable at `/admin/client-error-logs`).
- Payload: `message` (required), `stack`, `fatal`, `route`, `extra` (e.g. order_code, step).

## Dashboard UI (required)
- Every new admin entity MUST get a sidebar entry in `resources/js/Layouts/AppLayout.vue`
  using `SidebarLink`/`SidebarDropdown` with a related inline-SVG icon in the `#icon` slot.
- In list/table pages include related icons too: page-title icon, empty-state icon,
  and per-row icons/badges (e.g. payment/delivery status badges with matching colors).
- Test each new dashboard page with real example rows (create seed/test data) — verify
  icons, badges and statuses render correctly before considering the task done.

## Conventions
- Laravel 12. Migrations: anonymous classes in `database/migrations`.
- Enums: string-backed, per-domain folder under `app/Enums/<Domain>/` with `values()`, `getOptions()`, `getLabel()`, `label()`.
- Models: `$fillable` + `casts()` method, relations as methods.

## Orders Feature Notes
- `orders` table: statuses are DB enum columns backed by `App\Enums\Order\{PaymentStatusEnum,DeliveryStatusEnum}`; payment type by `PaymentTypeEnum` (`cod`, `transfer-wallet`).
- Delivery status updates come from an external delivery callback — must be logged on failure/success.
- Set `payment_status` AND `delivery_status` explicitly when creating an order. They have DB defaults, so the model returned by `create()` carries null and any resource reading `->status->value` blows up.
- `order_products` is an ARCHIVE, not a join table. Every line copies `name` (the whole translation map), `slug`, `image`, `quantity`, `old_price`, `new_price`, `line_total`, `cost_price` and `profit_price` at the moment of sale. Build lines with `OrderProduct::fromProduct()` — never read a placed order's prices back through `product_id` (which is nullable precisely so a deleted product does not take the order's history with it).
- `orders.delivery_cost` / `delivery_price` / `delivery_profit` archive the delivery arrangement an order was placed under, the way `order_products` archives `cost_price`/`profit_price` per line. `delivery_price` is added to BOTH `total_amount` and `total_amount_before_discount`, so the gap between them stays exactly the membership discount. The profit is ALWAYS recomputed as `price - cost` — by `StoreOrderRequest::delivery()` on the API and by `UpdateOrderAction` in the admin — never taken from whatever a caller or a form posted. The partner resource shows the buyer `delivery_price` only; cost and profit are ours.
- Storefront orders arrive at the key-gated `/api/v1/partner/orders`. `$request->ip()` there is the storefront's SERVER — the buyer's own IP and user agent come in the request body, which is why the endpoint needs the partner key.
- The order code is the buyer's only credential: random, 8 characters, ambiguous letters excluded (`Order::generateCode()`). Never make it sequential.
- Wallet receipts are a `receipt` media collection on `Order`: uncapped and APPEND-ONLY. Any number of files, added at any time, from the buyer's order page or the admin edit form — nothing in the app deletes or replaces one. An admin who does not believe a receipt moves `payment_status` (attributed and dated in `order_logs`); deleting the evidence records nothing. `Order::awaitingReceipt()` is "none yet" (the chase-the-buyer badge); `Order::acceptsReceipts()` is "more may be sent" (true for the life of a transfer order) — do not collapse them. A receipt is a CLAIM — it never moves `payment_status`; an admin confirms it against the wallet.

## Audit Logs (required)
- Every create/update/delete/status-change on orders and products MUST be recorded:
  - `OrderLog::record($orderId, $adminId, $action, $oldValues, $newValues, $request)` → `order_logs`
  - `ProductLog::record(...)` → `product_logs`
  (same pattern as `FacilityLog`: old/new values JSON + changed_fields + ip/user_agent).
- Reads count too: opening an order (`OrderLog::ACTION_VIEWED`) or its edit form
  (`ACTION_EDIT_VIEWED`) is filed by `OrderLog::recordVisit()`, which folds a repeat
  visit by the same admin inside `VISIT_THROTTLE_MINUTES` into the one already logged —
  otherwise a refreshed page buries every real change. A failed log must never cost the
  admin the page: warn and carry on.
- An order's `order_code`, `ip_address` and `user_agent` are never editable. The code is
  the buyer's only credential; the other two are the only record of where the order came
  from (the buyer's, forwarded by the storefront — not the caller's).
