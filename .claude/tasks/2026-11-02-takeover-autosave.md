# Takeover of membership number: auto-save the member

## المطلوب (حرفياً)
"انا عايز فى الاعضاء لما اغير رقم العضويه واخلص كل الاجرائات واوافق التغيير يتم التغير والحفظ من غير ما اضغط زرار التعديل للعضو نفسه واشوف اشعار ان العضو تم التعديل عليه"

## PROGRESS
- [x] قراءة الكود
- [x] MemberForm.runTakeover: بعد release في التعديل بينادي memberStore.updateMember() (toast "Member updated successfully" + رجوع للقائمة)؛ الإنشاء يفضل بالزرار
- [ ] اختبار على DB _test فقط
