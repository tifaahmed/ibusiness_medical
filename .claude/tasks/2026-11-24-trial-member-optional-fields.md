# Trial member: national ID / governorate / city optional

## المطلوب (حرفياً)
"فى الاعضاء لما اخلى عضو عضويه triall  ساعتها عادى اقدر انى ادخله من غير رقم البطاقه ومن غير المحافظه والمدينه  بحيث الخانه تتحول optional  فى الباكاند والفرونت"

## القرارات
- لما العضو يتعلّم Trial في فورم الأدمن (إنشاء/تعديل): national_id و governorate و city تبقى اختيارية (باكاند: Store/UpdateMembershipRequest، فرونت: فورم العضو). لو مش trial تفضل إجبارية زي ما هي.
- Phone/National ID uniqueness ما يتغيرش (لو اتكتب يتفحص).

## PROGRESS
- [x] فحص: الباك إند (Store/UpdateMembershipRequest: national_id/governorate/city nullable لما is_trial) والـ zod في memberValidation.js كانوا شغالين أصلاً؛ الأعمدة nullable في DB
- [x] باكاند: مفيش تعديل مطلوب (Store request governorate/city nullable دايماً)
- [x] فرونت: MemberForm.vue — علامة required على الرقم القومي/المحافظة/المدينة بقت :required="!is_trial"
- [ ] اختبار بصري في المتصفح لم يتم (محتاج npm build/dev)
