# Link an admin account to a sales rep (/admin/sales/{id}/edit)

## المطلوب (حرفياً)
admin/sales/752/edit  انا عايز انى اقدر اربط حساب ادمن هنا  ولاززم الادمن يكون ليه حساب واحد بس سالس  والسالس واحد ادمن

## القرارات
- one-to-one: `sales.admin_user_id` nullable UNIQUE FK -> users (additive migration, nullOnDelete).
- Validation: user must be a non-member admin; not already linked to another sales (Rule::unique ignore self).

## PROGRESS
- [x] migration
- [x] Sales model relation + User relation
- [x] Store/Update requests + actions
- [x] Edit/Create controllers pass admin options; edit resource returns admin_user_id
- [x] Vue form select + store form field
- [x] php -l ok; SalesListFilterTest + QuickAdd tests cover the link
- [~] vite build running; migration NOT yet run (deploy: backup then php artisan migrate --force)

# 2026-11-04 sales update fails: PUT /admin/sales/752 -> name required (server saw no name)
- [x] SalesStore.updateSale: PUT+forceFormData -> POST with _method=put (same as MemberStore). Same pattern (unfixed) in ServiceTypeStore, PartnerStore, ServiceStore
- [~] vite build

## 2026-11-04 — بلاغ من المستخدم (حرفياً)
/admin/sales/752/edit ليه هنا بعد ما حددت الادمن مش ظاهر فى خانه ال select
- [ ] find why the chosen admin is not displayed in the select (SalesForm.vue FormSearchableSelect admin_user_id)
- [x] cause: SalesFormView watch(props.sale,{deep}) re-ran setSale after a rejected save and wiped the chosen admin; now only on a different rep id. Also Sales::adminOptions always lists the already-linked admin + label built without byte-trim(). Test added.
- [x] SalesListFilterTest 2 passed + vite build OK

## 2026-11-04 — بلاغ (حرفياً)
http://127.0.0.1:8004/admin/user/membership/quick-add -> QueryException Unknown column 'admin_user_id' in 'where clause' (select * from sales where admin_user_id = 1)
- السبب: migrations الجديدة ما اتطبقتش على القاعدة المحلية ibusiness_medical. الحل: mysqldump ثم migrate --force (إضافات فقط).
- [x] 7 pending migrations (3 from earlier sessions: member_notes, card_deliveries, member_kind; + quick add, sales.admin_user_id, message permissions/templates, whatsapp/extra phones) applied to LOCAL ibusiness_medical after a verified mysqldump (24MB, 86 tables): /tmp/claude-1000/-home-tifaa-projects-deilar-ibusiness-medical/e4f9838b-3474-4edf-9307-94b1073bc2b1/scratchpad/ibusiness_medical-before-2026-11-04.sql
- verified: sales.admin_user_id, member_extra_phones, 10 template rows exist; 0 pending
