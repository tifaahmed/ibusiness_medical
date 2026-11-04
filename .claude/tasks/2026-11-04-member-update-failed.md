# Member edit fails with generic "Failed to update user and membership" (member 2192, slug -1739)

## المطلوب (حرفياً)
هنا فى التعديل على العضو ظهر الارور ده من غير ما يكون واضح ايه المشكله
انا كل الى عملته انى شلت التحديد الخانات الاربعه الى هما ال checkbox ورفض التعديل من غير ابداء اسباب
(PUT https://card.deilar.com/admin/user/membership/-1739 ; is_active/is_visible/is_paid/is_trial all false; response errors.error = "Failed to update user and membership. Please try again.")

## PROGRESS
- [x] prod log is on the server (storage/logs/failures.log: run `php artisan logs:failures --examples` there) — local has no trace
- [x] controller now returns the real reason + ValidationException field errors (was generic)
- [ ] fix + show the real reason to the admin
- [ ] reproduce on phpunit test DB (is_active/visible/paid/trial all false on member update); waiting for QuickAdd test run to free DB
