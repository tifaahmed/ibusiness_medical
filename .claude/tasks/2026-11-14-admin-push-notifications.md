# Admin page: send a push notification to every app user (open or closed)

## المطلوب (حرفياً)
"انا كنت عايز اعمل ان مكان فى ~/projects/deilar/ibusiness_medical صفحه فى لوحه التحكم اقدر املى بيانات وارسل اشعار لكل الناس فى التطبيق سواء فاتحي التطبيق او لا يجى اشعار على الموبايل تبع التطبيق بتاعى اعملها وقولى لو محتاج اسجل فى مكان عشان تشتغل"

## القرارات
- No push infra existed. Built: tables device_push_tokens + push_notifications + permission; FcmClient (HTTP v1); job afterResponse (no worker); admin page /admin/push; app plugin @capacitor/push-notifications + src/utils/push.ts; Android already had google-services gradle hook; iOS AppDelegate handlers added.

## PROGRESS
- [x] explore
- [x] backend + admin page + app code written; FcmClientTest 3 pass; app tsc clean
- [ ] USER: create Firebase project, put google-services.json in android/app, upload service-account JSON, set FCM_PROJECT_ID, run migrate (mysqldump first), npm run build, rebuild app
- [ ] NOT RUN: migration, admin page in browser, real push to a phone
