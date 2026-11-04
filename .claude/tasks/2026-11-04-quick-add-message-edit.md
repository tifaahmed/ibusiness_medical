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

## 2026-11-04 — طلب (حرفياً)
/admin/user/membership/quick-add اظهر الرساله الثابته الى هتتبعت وانا اقدر عادى اعدل عليها لو ليا  صلاحيه
- [x] تحقق على القاعدة المحلية: الصفحة 200، can_edit_message=true للمستخدم 1 (super_admin)، المعاينة بترجع النص؛ لا تغيير كود لازم — الخطأ 500 السابق (عمود sales.admin_user_id) كان مخبيها

## 2026-11-04 — بلاغ (حرفياً)
QuickAddView.vue:91 Uncaught ReferenceError: Cannot access 'preview' before initialization (setup, watch getter) — http://127.0.0.1:8004/admin/user/membership/quick-add
- [ ] move const preview before the watch/computed that use it
- [x] fixed: const preview moved before the watch that reads it (QuickAddView.vue). SSR smoke can NOT catch this (watch getters don't run in SSR); a static use-before-define check over watch() sources (validated against the broken order) passes on all touched Vue pages. Rebuilding.

## 2026-11-04 — بلاغ (حرفياً)
http://127.0.0.1:8004/admin/setting/59/edit -> 504 (Outdated Optimize Dep) quill.js / @tinymce_tinymce-vue.js ; Failed to fetch dynamically imported module SettingEditView.vue
- السبب: SSR smoke test بتاعي (vite createServer من جذر المشروع) كتب فوق node_modules/.vite/deps الخاص بخادم dev المستخدم (14:14:54). 
- الحل: المستخدم يوقّف npm run dev ويشغّله npx vite --force (مفيش ملفات مشروع اتأثرت). محفوظ في الذاكرة.
