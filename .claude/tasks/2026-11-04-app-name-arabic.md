# Arabic app name = ديلر (not ديلار), exposed in .env for future use

## المطلوب (حرفياً)
اسم التطبيق بالعربي ديلر صلح ده و وضحه فى الenv   عشان الاستخدام المستقبلى

## PROGRESS
- [x] only app/Support/QuickAdd.php had it (4 places) -> now config("mobile_app.name_ar") = env APP_NAME_AR, default ديلر; added to .env + .env.example
- [ ] server: add APP_NAME_AR="ديلر" to its .env + php artisan config:cache
- note: ~/Downloads/quick-add-sms-messages.txt (review file) still has the old spelling, not touched
