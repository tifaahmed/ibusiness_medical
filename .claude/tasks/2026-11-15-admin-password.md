# تغيير باسورد admin@deiler.com

## المطلوب (بكلام المستخدم)
"خلى ديه الباسورد لده admin@deiler.com" — الباسورد الجديد اتبعت في الرسالة (مش متسجل هنا عمداً).

## القيود
- بدون حذف/استبدال بيانات غير الباسورد. افحص الحساب الأول.

## PROGRESS
- [ ] فحص وجود الحساب (DB + email)
- [ ] تغيير الباسورد (hashed عن طريق Eloquent)
- [ ] التحقق

# طلب جديد (2026-11-15)
"'/mnt/c/Users/tifaa/Downloads/card-20261115' use this as database for the current project"
- القرار: استيراد card-20261115.sql (فيه DROP TABLE) في DB جديدة `ibusiness_medical_card_20261115` وتوجيه .env عليها؛ `ibusiness_medical` القديمة ما تتلمسش.
- [x] DB + استيراد (97 جدول) - [x] .env اتعدل (backup: .env.bak.before-card-20261115) - [x] باسورد admin@deiler.com اتغير
