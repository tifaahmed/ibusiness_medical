# Admin Order — Show / Edit / Audit Log

Task: add a **show** page and an **edit** page for `/admin/order`, and record
every action *and every visit* in `order_logs`.

Status: **complete and verified** (see "Verified" below). Anything under
"Still open" was never part of the ask and is listed only so the next session
knows it was a decision, not an oversight.

Resume rule: tick a box only when the file exists and `php -l` / the build
passes.

---

## Done

### Backend
- [x] `app/Models/OrderLog.php` — added `ACTION_VIEWED`, `ACTION_EDIT_VIEWED`,
      `ACTION_PRODUCTS_CHANGED`, `VISIT_THROTTLE_MINUTES = 10`, and
      `recordVisit()` (skips a repeat visit by the same admin inside the window
      so a refreshed page does not bury real changes).
- [x] `app/Http/Resources/Admin/Order/Show/AdminOrderLogResource.php`
- [x] `app/Http/Resources/Admin/Order/Show/AdminOrderProductResource.php`
- [x] `app/Http/Resources/Admin/Order/Show/AdminOrderShowResource.php`
- [x] `app/Http/Resources/Admin/Order/Edit/AdminOrderEditResource.php`
      (statuses as bare values — a select must not bind to `{value,label}`)
- [x] `app/Http/Controllers/Admin/Order/Show/AdminOrderShowController.php`
      (bound by `order_code`; logs `viewed`, best-effort)
- [x] `app/Http/Controllers/Admin/Order/Edit/AdminOrderEditController.php`
      (logs `edit_viewed`; ships status options + catalogue picker)
- [x] `app/Http/Requests/Admin/Order/UpdateOrderRequest.php`
      (`cancel_reason` required when payment_status = canceled)
- [x] `app/Http/Controllers/Admin/Order/Actions/Update/UpdateOrderAction.php`
      (one transaction: order + lines + audit rows; separate log rows for
      payment/delivery status, cancellation and line changes)
- [x] `app/Http/Controllers/Admin/Order/Update/AdminOrderUpdateController.php`
- [x] `routes/web.php` — `admin.order.show`, `admin.order.edit`,
      `admin.order.update` (show/edit gated as list; edit+update need
      `manage orders|manage own orders`)

### Frontend
- [x] `resources/js/Pages/Admin/Order/orderDisplay.js` — shared badge palettes,
      status labels, price + translated-name helpers
- [x] `resources/js/Pages/Admin/Order/Show.vue`
- [x] `resources/js/Pages/Admin/Order/Show/OrderShowView.vue`
- [x] `resources/js/Pages/Admin/Order/Show/OrderLogTimeline.vue`
- [x] `resources/js/Pages/Admin/Order/Stores/OrderStore.js`
- [x] `resources/js/Pages/Admin/Order/_components/Form/OrderForm.vue` + `index.js`

---

### Frontend (cont.)
- [x] `resources/js/Pages/Admin/Order/Edit/OrderEditView.vue`
- [x] `resources/js/Pages/Admin/Order/List/OrderListTable.vue` — Actions column
      (View always, Edit only for `manage orders|manage own orders`/super_admin)
- [x] `lang/en/admin.php` + `lang/ar/admin.php` — all new `order.*` keys, plus
      `common.remove` and `common.saving` (they did not exist)
- [x] `AGENTS.md` — audit-log section now covers visits and the read-only fields

### Verified
- [x] `npx vite build` — OrderShowView / OrderEditView / OrderForm /
      OrderLogTimeline all compile
- [x] `tests/Feature/Admin/OrderShowEditTest.php` — 10 tests, all passing
- [x] No regressions: `OrdersApiTest` (7) + `OrderListTest` (4) still pass
- [x] `vendor/bin/pint --test` clean on every new PHP file
- [x] Smoke-tested against the real dev database (order `DL-AFH6MJGD`):
      `/admin/order/{code}` and `/admin/order/{code}/edit` both 200, and the
      `viewed` / `edit_viewed` rows landed in `order_logs`

---

## Still open (nice to have, not blocking)

- [ ] Deleting an order from the admin (`ACTION_DELETED` exists on the model but
      no route uses it) — not asked for, so not built.
- [ ] Attaching/removing a wallet receipt from the admin side; today receipts
      only arrive over the partner API and the admin can view them.
- [ ] `order_logs` has no admin-facing list of its own; the trail is only shown
      per order on the show page.
- [ ] Order lines are edited on one page with no per-line undo — a removal is
      confirmed with `confirm()` and then logged, nothing more.

---

## Decisions worth keeping (why it is built this way)

- **Route key is `order_code`, not the id** — it is the key the order is known
  by in the table, on the phone and on the buyer's side.
- **`order_code`, `ip_address`, `user_agent` are read-only.** The code is the
  buyer's only credential; the other two are the only record of where the order
  came from (the buyer's, forwarded by the storefront — not the caller's).
- **`line_total` is always recomputed** from quantity × unit price server-side;
  the form never posts it, so it cannot drift.
- **Posted `products` array is authoritative** — a line missing from it was
  removed by the admin and is deleted, not zeroed.
- **`total_amount` is stored, not derived** — the membership discount lives in
  the gap between the lines and what was charged, so the form *offers* the
  lines total instead of applying it.
- **Visits are logged but throttled** (10 min per admin per order per action),
  and a failed log never costs the admin the page — it is warned about instead.
