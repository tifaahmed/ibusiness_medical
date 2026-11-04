# Quick add: permission to edit one customer's message + permission to edit the general templates (in site settings)

## المطلوب (حرفياً)
admin/user/membership/quick-add  انا عايز انى اقدر ادى صلاحيه  ان  شخص يقدر يعدل على الرساله للعميل الواحد
وعايز صلاحيه  تانيه انى اعدل عموما على نص الرساله الى هتتبعت عموما للعملاء بكب الحاللات
اعملى مكان اقدر اعمل التعديلات على الرساله ديه يفضل من جدول اعدادات الموقع

## القرارات
- Rule (AGENTS): new permissions added by MIGRATION (not PermissionSeeder); super admin passes via Gate::before; nothing deleted/overwritten.
- Templates live in the settings table (QuickAdd::seedRows pattern), edited from /admin/setting; defaults = current wording.

## PROGRESS
- [ ] explore settings page + permission enum + QuickAdd seeding
- [x] enum perms + QuickAdd templates (settings rows quick_msg_*) + migration 2026_11_04_150000
- [x] settings gating (SettingAccess; routes edit/update/list/show accept templates perm)
- [x] controller/action override + Vue textarea/counter + tests/Feature/Admin/QuickAddMessageEditTest.php
- [x] run tests + vite build
- [x] fixed: templates stored trimmed (TrimStrings), message() joins sentences with one space
- [x] UpdateSettingRequest::authorize uses SettingAccess (403 before validation). All 3 QuickAddMessageEditTest pass; QuickAddMemberTest 11 pass; vite build OK (before the PHP-only fixes)
