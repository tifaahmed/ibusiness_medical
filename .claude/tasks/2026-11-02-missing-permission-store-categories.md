# 500 on /admin/admin-users/14 update: permission `manage store categories` does not exist

## المطلوب (حرفياً)
"http://127.0.0.1:8004/admin/admin-users/14/edit — Spatie\Permission\Exceptions\PermissionDoesNotExist: There is no permission named `manage store categories` for guard `web`. PUT /admin/admin-users/14 (AdminUserUpdateController.php:81 syncPermissions)"

## PROGRESS
- [~] تحديد الصلاحيات الناقصة (enum vs permissions table) + migration يضيفها (add-only، مش PermissionSeeder)
- [x] migration 2026_11_02_120000_add_store_category_permissions (3 صلاحيات ناقصة: manage/manage own/view store categories) — add-only؛ اتشغلت على القاعدة المحلية بعد mysqldump للجداول (scratchpad/perm-backup-2026-11-02.sql)؛ اختبار StoreCategoryPermissionsTest
- [ ] على السيرفر: mysqldump ثم php artisan migrate --force (مش PermissionSeeder)
