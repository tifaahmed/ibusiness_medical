# لوحة إرسال إشعارات للموبايل (للكل أو أعضاء محددين)
## المطلوب (حرفياً)
"انا عايز مكان فى الداشبورد اقدر منه ابعت اشعار لكل الناس او احدد ناس محددين يجيلهم الاشعار من الاعضاء"
## القرارات
- (يتحدد بعد فحص الموجود)
## PROGRESS
- [ ] فحص الموجود (PushTokenController، أي صفحة إشعارات في الأدمن)
## اللي اتلقى
- الصفحة موجودة من 11-14: /admin/push (permission `send push notifications`)، للكل أو عضو واحد بالتليفون.
## اللي اتعمل (11-15)
- [x] "أعضاء محددين": textarea بأرقام (سطر/فاصلة، لحد 500) — PushNotification::parsePhones، الكنترولر، الجوب، الـ Vue، lang en/ar
- [x] migration 2026_11_15_110000_widen_push_audience_phone (text)
- [x] tests/Unit/PushAudiencePhonesTest
- [ ] لسه: migrate على السيرفر (mysqldump الأول)، npm run build، ملف service-account، تجربة فعلية
