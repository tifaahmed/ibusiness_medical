# Quick add: success page + WhatsApp send page + extra phone numbers on the member

## المطلوب (حرفياً)
عايز بعد الانشاء اشوف صفحه فيها زرار للتوجه للتعديل زرار التوجه للمشاهده وزرار للعوده وزرار لارسال  البيانات للواتسابب ولو اخترت الواتس افتح صفحه الاول ظاهر فيها محتوى الرساله مع صوره الكارت  ورابط التطبيق الموبايل او الابل لو موجود وكل التفاصيل

وممكن لو الرقم الهاتفى مش مرتبط ب واتس انى اضيف رقم تليفون تانى عليه الواتس ابب عشان ابعت البيانات للعميل

والرقم التليفون الثانى ده عايز احطه للعضو كا ارقام اخرى تابعه ممكن يبقى اكتر من رقم عادي ولكن الارقام الاضافيه ديه ملهاش استخدام مجرد كا ملاحظه مثلا

## القرارات
- Supersedes the earlier "after save open member edit" (user now wants a success page with buttons).
- Extra numbers: new table (additive migration), many per member, notes only: NOT users.phone, NOT unique, NOT used for login/SMS.
- WhatsApp has no send API here: wa.me link with the text; card image shown on the preview page (and its link put in the text if public).

## PROGRESS
- [ ] explore: card image source, member show/edit pages, existing extra tables

## 2026-11-04 — إضافة من المستخدم أثناء الشغل (حرفياً)
وافتكر انى بردو عايز انى اقدر اتحكم فى رسايل الواتس بنفس الطريقه الخاصه بالتليفون ونفس تنوع الصلاحيات ولكن  الخانه هتكون text editor
- قرار: رسائل الواتس = قوالب في جدول settings (wa_msg_*) تتعدل من /admin/setting + صلاحيتين مثل SMS:
  `edit whatsapp customer message` (تعديل رسالة عميل واحد في صفحة الواتس) و `edit whatsapp message templates` (القوالب العامة).
- "text editor" = محرر نص متعدد الأسطر بأزرار تنسيق الواتس (*غامق* _مائل_ ~مشطوب~) لأن الواتس مبيفهمش HTML؛ مش محرر HTML.

## PROGRESS (continued)
- [ ] migration: member_extra_phones (soft delete) + 2 WA permissions + wa_msg_* rows
- [ ] QuickAdd::whatsappMessage + SettingAccess for wa slugs
- [ ] controller: done page, whatsapp page, extra phones add/remove; redirect after store -> done page
- [ ] Vue: DoneView, WhatsAppView (CardPreview + links + number picker + editor), ExtraPhones on member show
- [ ] tests + build
- [x] migration 2026_11_04_160000 + model MemberExtraPhone + enum + QuickAdd::whatsappMessage + SettingAccess (per-slug permission)
- [x] QuickAddAfterController + routes (done, whatsapp, extra-phones store/destroy); store() redirects to done page; test updated
- [x] Vue: DoneView, WhatsAppView, ExtraPhones
- [x] extra phones on member Show page; tests/Feature/Admin/QuickAddAfterTest.php written
- [x] QuickAddAfterTest 3 passed (after vite build finished; running tests DURING a build fails with "Unable to locate file in Vite manifest") + QuickAddMessageEditTest + QuickAddMemberTest passed

## 2026-11-04 — بلاغ (حرفياً)
http://127.0.0.1:8004/admin/user/membership/yyyyyyyyyyy/whatsapp لما دخلت هنا الصفحه باظت والجهاز هنج وكان حاجه infinit loop او حاجه كبيره اوى بتتحمل ضربت الصفحه
- [x] loop reproduced in a reactivity-only simulation (old config: Maximum recursive updates exceeded; fixed config: 1 paint). No vite build run (user's dev server is up; HMR picks the change up).
- [x] cause: CardPreview watch returned a NEW array each run + WhatsAppView stored the canvas in a ref and passed an inline membership literal -> repaint<->re-render loop. Fixed in CardPreview (watch on one JSON string) and WhatsAppView (computed membership, non-reactive canvas, boolean cardReady).
