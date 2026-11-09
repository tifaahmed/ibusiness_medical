# زرار "Add" في صفحة الباتش يفتح إضافة عضوية برقم الباتش

## المطلوب (حرفياً)
"/admin/membership-card-patches/225 هنا انا عايز اضيف اشخاص واشتغل العضويات ديه بالارقام ديه او التعديل مش شغال حط بدابه زرار الاضافه عشان بشكل تلقائي اروح لمكان الاضافه للعضويه والرقم الجديد يكون موجود فى خانه رقم العضويه admin/user/membership/create"

## القرارات
- الأرقام اللي ملهاش عضو (is_unissued) زرار Edit بتاعها معطّل → نحط مكانه Add يروح لـ admin.user.membership.create?membership_number=N
- MemberCreateView.onMounted يقرأ ?membership_number ويملى الخانة.

## PROGRESS
- [x] قراءة الكود (Show.vue, MemberCreateView.vue)
- [x] زرار Add في Show.vue
- [x] تعبئة الخانة (await initializeForm ثم ?membership_number)
- [x] vite build تم؛ لم يُجرَّب في المتصفح (محتاج تأكيد من المستخدم)
