# Card batches: show taken numbers + "skip" button

## المطلوب (حرفياً)
admin/membership-card-patches/create "انا عايز هنا انى لو بعمل كروت وكانفى السلسله الارقام كان فيه ارقام اتاخدت قبل كده يظهر الاشعار بقايمه الارقام الى اتاخدت قبل كده فا فى الحاله ديه اوس على زرار يعمل تخطى للارقام الى بالفعل اتعملت قبل كده"

## التصميم
- الدفعة بتحجز مدى متصل (start..start+qty-1). التخطي = عمود جديد nullable `membership_card_patches.skipped_numbers` (json، إضافة فقط): أرقام جوه المدى مش بتاعت الدفعة. المدى = start + qty - 1 + عدد المتخطى.
- `BatchNumbers::covers/numbersOf` يستثنوا المتخطى (الأرقام المتخطاة مش محجوزة للدفعة).
- `GET admin.membership-card-patches.check` يرجّع الأرقام المأخوذة (عضويات + دفعات تانية) في المدى؛ الفورم يعرضها + زرار "تخطي".
- الـ store: `skip_taken=true` يخلي الأكشن يتخطى بدل ما يرفض. من غيره الرفض (422) زي الأول.

- طلب إضافي من المستخدم: "خيار إخراج الكروت بأرقام عشوائية، مش لازم رقم بداية ولا تسلسل" → عمود `number_list` (json) للدفعة العشوائية؛ الأرقام بتتسحب من السيرفر (6 أرقام، حرة) وتتعرض قبل الإنشاء.

## PROGRESS
- [x] migration (skipped_numbers + number_list) اتشغلت محلياً (إضافة فقط) + dump بعدها: ~/db-backups/ibusiness_medical_card_20261117_after_skipped_numbers_migrate.sql (الـ dump اللي قبلها فشل بسبب باسورد، والمجرات اتشغلت قبل ما أتأكد — غلطة مني، المجرات إضافة فقط)
- [x] BatchNumbers (plan/randomNumbers/heldAmong/covers/numbersOf/endNumber) + check endpoint + request + action
- [x] Create.vue (تنبيه الأرقام المأخوذة + زرار تخطي + وضع عشوائي) + ziggy
- [x] متحقق بـ tinker جوه transaction + rollback
- [ ] لم يتم النظر في الصفحة بالمتصفح؛ لم يتم عمل build؛ مفيش اختبار آلي؛ مش committed
