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

## Permissions & the super admin

- **`super_admin` passes every permission check, always** — via `Gate::before` in `AppServiceProvider`, on the strength of the ROLE, not on what happens to sit in `role_has_permissions`. Before that it only held whatever `PermissionSeeder` last synced, so adding a permission to `UserPermissionEnum` and shipping the feature that used it locked the super admin out until somebody re-ran the seeder. That is exactly how `manage settings` came to 403 the one account meant to reach everything. Adding a new permission to the enum now needs no seeder run for the super admin to have it.
- The callback returns **null**, not false, for everyone else. False is a verdict and would end the check before the real permission lookup ran; null means "no opinion, carry on".
- **Ask the Gate, never the permission tables, when the answer must respect that.** `can()` / `canAny()` run the Gate; Spatie's `hasPermissionTo()` / `hasAnyPermission()` read `role_has_permissions` directly and cannot see the super-admin grant. `EnsureAdminAreaAccess` used `hasAnyPermission` and so turned a super admin away at the door of the admin area even though every route inside would have let them in — it uses `canAny` now. Spatie's own `permission:` route middleware already uses `canAny`, which is why routes worked and the area gate did not.
- `User::effectivePermissionNames()` is what the front end gets (`authUserPermissions` in `HandleInertiaRequests`, `auth.user.permissions` in `ShareUserPermissions`) — the full enum for a super admin, the granted rows for everyone else. Do not go back to `getAllPermissions()` in those two places: the admin UI hides its buttons on this list, so a list narrower than the Gate gives a super admin routes they can reach by typing the URL behind links the page will not draw.
- `permissions` rows still matter for every OTHER role, so a new permission must still be added to `UserPermissionEnum` and seeded (`php artisan db:seed --class=PermissionSeeder`) before a non-super role can be granted it. Run it after adding one.
- `manage own contact messages` exists as a row in `permissions` but is deliberately absent from `UserPermissionEnum::all()` — see the Contact Enquiries notes below. It is a leftover, not a gap; do not "fix" it by re-adding it to the enum.
- Regression cover: `tests/Feature/Admin/SuperAdminPermissionsTest.php`.

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

## Shipping with ABS Courier (order → courier)

- The button lives on the order SHOW page and calls `POST /api/v1/create-shipment`, **not** `create-pickup`. `create-pickup` takes AWBs that already exist and only schedules a collection — it cannot start a delivery. Passing `locationId` (from `ABS_LOCATION_ID`) on `create-shipment` books the pickup in the SAME call, so one request both ships the parcel and asks for it to be collected. Do not add a second `create-pickup` call.
- `orders.customer_governorate` / `customer_city` are free Arabic text the buyer typed; ABS routes on numeric `governorateId` / `cityId`. `AbsAddressMatcher` narrows the ABS dropdown to a best guess (alef/ta-marbuta/ya variants folded, the `ال` article ignored, Arabic-Indic digits normalised) and the admin confirms it in the dialog. It returns null on an AMBIGUOUS containment match rather than picking the first row — a wrong governorate sends a parcel to the wrong end of the country. `ShipOrderRequest` requires both ids and never reads them off the order: the confirmation IS the authorisation.
- `AbsShipmentPayload` is the ONE mapping from our columns to their fields. The preview and the submit both build through it, so what an admin approves on screen is what gets posted — a second mapping written "just for the preview" is a preview that can lie.
- `cash` (COD) is set outside the payload's `array_filter` on purpose: zero is a real instruction ("collect nothing, it is paid"), and filtering it out turns a prepaid order into one with no COD instruction at all. Only `PaymentTypeEnum::COD` collects anything; a wallet transfer sends `0`. The figure is `total_amount - total_paid`, floored at zero.
- Phones are normalised to E.164 (`01070274943` → `+201070274943`); a number already starting `+` is left alone so a foreign number is never rewritten into an Egyptian one.
- Never send `awb`, `businessLocationId` or any branch id — ABS derives all of them, and the spec says not to.
- `ShipOrderAction` calls ABS **outside** any transaction, then writes the order and its logs inside one. An HTTP call must not hold row locks, and a refusal must leave the order untouched. `abs_awb` is UNIQUE and is the guard against double-booking (two couriers, two delivery fees). The one unfixable case — ABS accepts and our write fails — is logged at error level with the AWB and surfaced to the admin as "do NOT ship it again", because it can only be reconciled by hand.
- A 2xx carrying no AWB is treated as a FAILURE. An order marked shipped with nothing to track is worse than one that visibly failed to ship.
- Shipping writes `OrderLog::ACTION_SHIPPED` (with the AWB and the ids actually booked, so a misdelivery can be traced to a bad match vs a bad address) plus the usual `delivery_status_changed`. Status goes to `processing`, never `on-delivery` — that is the courier's news to tell.
- Leaving `ABS_API_KEY` unset hides the button (`AbsClient::isConfigured()`, the `absConfigured` page prop), the same way a missing Gemini key hides the AI helpers. `ABS_BASE_URL` defaults to STAGING so a half-configured install cannot book a real courier.
- Tests: `tests/Feature/Admin/OrderShipTest.php`, entirely behind `Http::fake()`. Nothing in that file may ever reach the real API — a leak books an actual courier to a real customer's address.

## Contact Enquiries Feature Notes
- `contact_messages` is the ONE inbox for every public form, this site's and the Deilar storefront's. `source` (`ContactSourceEnum`) is what tells them apart: `contact_form`, `card_popup`, `join_request`. Sales read the three differently, so it is a first-class filter, not something to search for.
- Storefront enquiries arrive at the key-gated `POST /api/v1/partner/contact-messages`. `$request->ip()` there is the STOREFRONT's server — the visitor's own `ip_address`, `user_agent`, `locale` and `referrer` come in the request body, exactly as they do for partner orders. That is why the endpoint needs the partner key.
- Write through `App\Actions\Contact\RecordContactMessageAction` — never `ContactMessage::create()` directly. It writes the row and its opening `received` log entry in one transaction, so an enquiry can never exist without a starting point in its log, and it tells the inbox. A mail failure is caught and logged there rather than thrown: the row is already committed, and a 500 makes the storefront queue a duplicate.
- Admin edits go through `App\Actions\Contact\UpdateContactMessageAction`, which writes one `contact_message_logs` row per field that actually moved. A salesperson is logged by NAME (so the trail survives that salesperson being deleted); a status by its VALUE (so it can be re-translated for whoever reads the log later). Never log a label.
- Statuses are a sales PIPELINE — `new` / `in_progress` / `resolved` / `closed` — not an inbox's read/unread. `read_at` records the first time an admin opened it and moves nothing; `replied_at` is stamped the first time it reaches `resolved`.
- `commercial_register` belongs to `join_request` only: it is what sales verifies an applying facility against, so it appears in the list, the detail page and the inbox mail.
- `created_at` is accepted on the partner endpoint for the storefront's one-off backfill only, so a copied enquiry keeps its real age. The timestamps are deliberately NOT fillable; the action sets them on the instance.
- Permissions: reads (`index`/`show`) need `manage contact messages|manage memberships|view contact messages`; writes (`update`/`destroy`/`bulk-update`) need `manage contact messages|manage memberships`. `manage memberships` is kept on both so admins who could work the inbox before it had its own permission do not lose it. Both controller actions pass `canManage` to the page, and `Show.vue` hides the whole manage card behind it — the viewer role holds `view contact messages`, so it reaches the page with no right to change anything.
- There is no "manage own contact messages". It used to sit in `pairs()`, was offered on `/admin/roles/create` and enforced nowhere; an enquiry comes from a public form and has `created_by = null`, so nobody can ever own one. `MANAGE_CONTACT_MESSAGES` is now in `standalone()`. Do not re-pair it.
