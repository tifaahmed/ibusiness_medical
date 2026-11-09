# تعارض رقم التليفون في فورم العضو: عرض الشخص الآخر + حذفه + نقل الرقم

## المطلوب (حرفياً)
"http://127.0.0.1:8004/admin/user/membership/mstf-ahmd/edit This phone number already belongs to another member. فى الحاله ديه اظهرلى زرار لما ادوس عليه اشوف بيانات الشخص التانى ولو ملوش عضويه مجرد شخص بلا عضويه اظهرلى خيار انى احذفه اصلا غير كده انا عايز خيار انى اخلى رقم تليفونه حاجه تانيه واخد انا الرقم ده وخيار انى اخد رقم تليفونه واسيبه من غير تليفون"

## القرارات
- الكتابة كلها عبر Eloquent save (قاعدة الفريدة)، حذف soft فقط، وبتأكيد، ومسجّل في اللوج.

## PROGRESS
- [x] قراءة + التصميم: Action TakeOverPhone + POST /api/member-field/phone/release + checkField يرجع takeover
- [x] backend كُتب (Action + controller + route)
- [x] واجهة PhoneConflict.vue + ترجمات ar/en + vite build
- [x] PhoneTakeoverTest 5/5 على membership_test (تأكدت من اسم الـ DB قبل التشغيل)
- [ ] لم يُجرَّب في المتصفح
