# طلب: أعمدة المدفوع في قائمة الطلبات (admin/order)
## المطلوب (حرفياً)
"لما دفعت تمن طلب بالكامل عن طريق نقاط الولاء ليه الطلب ظهر عندى انه حاله الدفع قيد الانتظار. وكمان هنا تكلفة الطلب عايز اشوف الفلوس الى اندفعت كم ولاء وكم محفظه وكم فلوس حقيقيه بحيث يكون فيه 3 عواميد زياده جنب تكلفه الطلب"
## التشخيص
- DL-UE4HGMMX: total 585 = منتجات 550 + توصيل 35. النقاط تغطي المنتجات فقط (PointsPayments: never delivery/COD fee) فالباقي 35 حقيقي => pending صح.
## PROGRESS
- [ ] 3 أعمدة (نقاط / محفظة / حقيقي) في OrderListTable.vue بدل الـtags
- [ ] "المتبقي" يظهر في الحالة لو الطلب مغطى جزئياً
- [x] 3 أعمدة (نقاط/محفظة/حقيقي) + real_due + اختبار OrderPaymentBreakdownTest (شغال في الخلفية)
## طلب إضافي (رسالة المستخدم)
"عايز سطور الطلبات فى الجدول اقدر احدد ان الطلب ده انتهى والفلوس حقيقيه بحيث اقدر اميز انى مش هفتحه تانى وممكن اخليه بالاسود بحيث انه اختبار مش عايز افتحه تاني"
قرار: عمود `orders.admin_mark` (null | done | test) — إضافي، من غير حذف؛ زر في الصف يبدّل العلامة؛ done = أخضر خافت، test = أسود/رمادي غامق.
- [ ] migration + model fillable  - [ ] route/controller  - [ ] UI في OrderListTable  - [ ] test
- [x] migration/controller/route/UI/lang كتبوا؛ test OrderMarkTest كتب — لسه يتشغل + npm build check
- [x] OrderPaymentBreakdownTest + OrderMarkTest نجحوا. الواجهة (Vue) ما اتجرّبتش بصرياً؛ لازم npm run build على السيرفر + php artisan migrate (admin_mark).
