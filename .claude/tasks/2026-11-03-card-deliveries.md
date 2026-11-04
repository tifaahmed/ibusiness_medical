# Card deliveries + delivery couriers (تسليم الكروت ومناديب التوصيل)

## المطلوب (حرفياً)
"عايز احط فى العضو  مكان فى تابه منفصله لو استلم كارت   احط مينن الى سلم الكارت ومندوب التوصيل انا عايز جدول جديد احط فيه كل مناديب التوصيل فى الموقع  بحيث ان ممكن نبعت مندوب توصيل يسلم الكارت اول مره

وعايز انى اقدر اسجل كذا تسليم للعميل عشان لو العميل بعدين طلب كذا كارت بعدين اقدر اسجل كل عمليه تسليم وملاحظات عليها وصور الكروت الى اتسلمت كل مره  ومين المندوب الى سلم والملاحظات الى يكتبها

عايز مكان منفصل اشوف فيه عمليات توصيل الكروت كلها فى جدول منفضل لكل الناس  واقدر اعدل عليها براحتى واحط كذا ملاحظه على كل عمليه توصيل"

## القرارات (مبدئية)
- جدول `delivery_couriers` (مناديب التوصيل: اسم، تليفون، ملاحظات، active) + صفحة إدارة + sidebar.
- جدول `card_deliveries` (user_id العضو، courier_id، delivered_at، notes، created_by) + صور الكروت عبر media collection (Spatie) + `card_delivery_notes` (ملاحظات متعددة لكل عملية، مين كتبها).
- تابة منفصلة "تسليم الكروت" في صفحة العضو (عمليات متعددة) + صفحة مستقلة بالناف بار بكل العمليات لكل الناس، قابلة للتعديل.
- Migration تضيف فقط. صلاحيات جديدة بمigration مش seeder. تست على membership_test فقط.

## PROGRESS
- [x] migration + 4 models + permissions enum + CardDeliveryController + DeliveryCourierController + routes (php -l تمام)
- [x] واجهات: CardDeliveries/{Index,Couriers,DeliveryForm,DeliveryItem}.vue + قسم في Show.vue + sidebar (كُتبت، فاضل build+مراجعة)
- [ ] تست + vite build + مراجعة المتصفح
- [ ] أسئلة مفتوحة: التعديل لأي أدمن عنده الصلاحية (مش للكاتب بس)؟ — افتراضي: أي أدمن معاه الصلاحية يعدل

- [x] CardDeliveriesTest نجح (2/2) على membership_test + vite build تمام؛ الـ routes في مجموعة بدون manage memberships
- [ ] لسه: مراجعة المتصفح (موبايل/تابلت/ديسكتوب + RTL)، ترجمة عربي، قرار: تعديل التسليمة لأي أدمن ولا للكاتب بس

## التشغيل
- build: `npm run build` (بياخد >2 دقيقة)
- تست: `APP_CONFIG_CACHE=/tmp/nocache.php DB_CONNECTION=mysql DB_DATABASE=membership_test php artisan test tests/Feature/Admin/CardDeliveriesTest.php tests/Feature/Admin/MemberNotesTest.php` (بياخد ~2.5 دقيقة، تأكدت إن الـ DB = membership_test)
- لسه migration ما اتعملتش على الـ live DB — الـ deploy: backup ثم `php artisan migrate --force` فقط
