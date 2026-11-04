# Member notes (ملاحظات الأعضاء) — per-member notes + a sidebar page with all notes

## المطلوب (حرفياً)
"انا عايز  انى احط للعضو مكان انى اسجل فيه ملاحظات عليه تبعه واخلى ان محدش يقدر يعدل غير ملاحظاته هوا ولكن الكل شايف ملاحظات بعض وعايز زرار منفصل فى الناف بار فيه كل الملاحظات الى اتحط على كل الاعضاء واقدر اضيف منها  ومين الى عمل الملاحظه وايه هيا الملاحظه"

## القرارات
- جدول جديد `member_notes` (user_id = العضو، created_by = الأدمن، body). Migration تضيف فقط + permissions `view member notes` / `add member notes`.
- التعديل/الحذف للكاتب فقط (حتى السوبر أدمن مايعدلش ملاحظة غيره) — مفروض في الـ controller مش في الـ UI بس.
- الكل (اللي معاه view) يشوف كل الملاحظات. الإضافة من صفحة العضو ومن صفحة الناف بار (اختيار العضو بالبحث).

## PROGRESS
- [x] قراءة الكود
- [x] migration + model + enum + controller + routes + Vue (Index, NoteItem, قسم في Show) + sidebar + MemberNotesTest (المنطق نجح؛ باقي vite build لـ manifest)
- [ ] قسم الملاحظات في صفحة العضو (Show.vue)
- [ ] صفحة /admin/member-notes + sidebar link
- [ ] تست على قاعدة `_test` فقط (sqlite memory أو membership_test) + vite build
- [ ] مراجعة في المتصفح (موبايل/تابلت/ديسكتوب + RTL)

- [x] تست MemberNotesTest نجح (2/2) على membership_test بعد نقل الـ routes لمجموعة بدون manage memberships
