# Upload navbar logo from admin/setting
## المطلوب (حرفياً)
admin/setting  حط مكتن ارفع لوجو عشان اللوجو الى فى الناف بار
## PROGRESS
- [~] find navbar logo source
- [x] POST /admin/setting-logo (AdminSettingLogoController -> deilar_logo image setting, old file kept) + upload card on SettingListView.vue (shows current appLogo)
- [ ] verify in browser; config/route cache on host: php artisan route:clear if cached
