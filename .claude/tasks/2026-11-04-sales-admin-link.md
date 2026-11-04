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
