# Settings page grouped by relation
## المطلوب (حرفياً)
/admin/setting?page=5&sort=key  انا عايزها عناصر ومجمعه مع بعض  على حسب ارتباطهم ببعض
## PROGRESS
- [~] explore setting page (controller + vue)
- [x] App\Support\SettingGroups (prefix -> section), controller groups+lists all rows when sort=key (no pagination), resource adds group+meta.grouped, table draws section header rows
- [ ] verify in browser (vite not run); newest/oldest sorts stay paginated flat
