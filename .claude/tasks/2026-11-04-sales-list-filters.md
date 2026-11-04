# /admin/sales: filters for everything + linked admin shown and filterable

## المطلوب (حرفياً)
admin/sales  حط فلاتر هنا لكل الافكار وحط فى صفحه المشاهةه الادمن المرتبط  وفلتر بيه

## القرارات
- "لكل الافكار" = every useful idea/column of the list (name, linked admin yes/no + which admin, has image, created by, dates, counts of facilities/members/stores...). Decide after reading the list page.
- Show linked admin on list rows + show page (sales.admin_user_id); filter by it.

## PROGRESS
- [x] read: SalesListFilterContent.vue exists but is NOT mounted in SalesListView; list has only search
- [x] backend filters (admin yes/no, admin_id, image, facilities/members/stores yes/no, creator, dates, sort) + counts + admin in list/show resources
- [x] list: filters mounted (SalesListFilterContent rewritten, old file unused before), admin+members+stores columns; show: admin + counts
- [x] SalesListFilterTest passed + vite build OK
