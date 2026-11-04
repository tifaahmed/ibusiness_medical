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
- [ ] verify (php -l, throwaway DB only - never live DB)
- [~] vite build running; migration NOT yet run (deploy: backup then php artisan migrate --force)
