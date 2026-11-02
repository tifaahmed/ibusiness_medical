# Dashboard-wide "Save and stay" button

## المطلوب (حرفياً)
"على مستوى الداشبرد كله حط خيار فى كل الفورم الى موجود انى اشوف زرار حفظ والبقاء ولما اضغط عليه بعد ما اضغط عليها احفظ البياانات مع اعاده تحميل للصفحه ولكن انا فى نفس الصفحه"

## القرارات
- زرار ثاني "حفظ والبقاء" جنب زرار الحفظ؛ يحفظ ويعيد تحميل بيانات نفس الصفحة (بدون redirect للقائمة).

## PROGRESS
- [x] حصر الفورم
- [x] RedirectBackWhenStaying (X-Save-Stay → redirect للـ edit من الـ Referer) على مجموعة الأدمن؛ composables/useSaveAndStay.js؛ Components/ui/SaveStayButton.vue (يظهر في صفحات /edit بس)؛ 22 store بقت تنادي afterSave؛ الزرار اتحط في 24 فورم تعديل (Store عنده زرار قديم)
- [ ] Facility Edit + Member + بقية الفورم اللي ملهاش type=submit؛ build + اختبار متصفح على DB _test
- [x] Facility عنده Save & Stay خاص بيه، Store كمان؛ ما اتغيروش. vite build نجح؛ SaveAndStayTest 3/3 (sqlite memory)
- [ ] لم يُختبر في المتصفح؛ صفحات الإنشاء (create) ما فيهاش الزرار (مفيش صفحة تعديل نعرف نروحلها من غير ما الـ controller يرجّع id)
