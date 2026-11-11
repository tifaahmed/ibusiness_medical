# Ledger rows show the related member (card no., name, phone)

## المطلوب (حرفياً)
"/admin/user/membership/-534/edit?tab=wallet هنا +35 referral commission #2466 ... By: the member / system ... انا يفتر اقدر اعمل رقم عضويه الشخص لاى سجل واسمه وتليفونه"
(= في سطور المحفظة بتاع الإحالة، اعرض رقم عضوية الشخص المرتبط بالسطر + اسمه + تليفونه بدل "#2466" و"By: the member / system")

## القرارات
- إضافي بس: الباك يرجّع `member {id,name,phone,card}` لكل سطر referral (من referral_rewards بالـ reward_key في client_id، وfallback #id من الـ note). مفيش تغيير في بيانات.

## PROGRESS
- [x] QuizLoyaltyController::relatedMembers + MemberWalletCard.vue chip (ledger-member)
- [x] textarea كبيرة لخانة السبب (wallet + loyalty)
- [ ] vite build تحقق (شغال في الخلفية)
