# Multiple cards per member

## المطلوب (حرفياً)
"انا عايز انى ممكن اخلى العضو الواحد سواء عضويه اساسيه او اقارب انى اقدر اديهم كذا كارت وكل كارت اما برقم مختلف او بنفس الرقم"

## قرارات
- الأدمن يعمل كروت فيزيكال وسوفت لأي عضو (أساسي/قريب)، بنفس الرقم أو رقم مختلف.
- العميل من التطبيق/الموقع: فيزيكال بنفس الرقم (موجود أصلًا، بند 16). الأدمن بعدها يغيّر الأرقام.
- جدول جديد member_extra_cards (add-only)، مش membership_cards (ده للـ card codes).

## PROGRESS
- [x] migration + MemberExtraCard + Membership::extraCards + matchingNumber/CardController/CardLoginController
- [x] AdminMemberExtraCardController + routes + قسم في Show.vue
- [x] test: tests/Feature/Admin/MemberExtraCardTest.php (+CardNumberLoginTest) نجحوا على ibusiness_medical_test
- [x] AGENTS.md بند 18 (ناقص نسخة deilar و deilar-mobile-new)
- [ ] لم يتعمل: مشاهدة القسم في المتصفح/vite build، ترجمات t.member.*، ربط طلب الفيزيكال بإنشاء كروت تلقائي، سوفت من العميل
- نشر: php artisan migrate --force بعد mysqldump

## طلب إضافي (2026-11-01)
"اعرف ان العميل يقدر يطلب الكروت ديه من الموقع والتطبيق عايز ده يكون مسمع معايا فى جدول الاعضاء عشان بستمرار اعرف العضو معاه كام كرت وانواعهم واقدر اعدل عليهم. مش متاكد ان العميل على الموقع العادى يقدر يطلب كروت ولا لو مش موجود حطه الى هوا هنا ~/projects/deilar/deilar"
- [x] طلب الفيزيكال من التطبيق/الموقع يتسجل تلقائي (status=requested) كصفوف في member_extra_cards (source=order, order_id)
- [x] جدول الأعضاء (MemberListTable badge + Show.vue تعديل status/kind/number): عمود عدد الكروت وأنواعها + تعديل
- [ ] فحص الموقع ../deilar: هل فيه طلب كروت فيزيكال؟ لو لا يتضاف
- [x] اختبارات PhysicalCardOrderTest + MemberExtraCardTest نجحت (6)
- [x] الموقع (action+controller+routes+member-physical-cards.tsx+translations+test نجح) ../deilar: لا يوجد طلب كروت فيزيكال — بنبني controller + page + routes يستخدموا /api/v1/mobile/physical-cards بتوكن العضو
- [x] AGENTS.md بند 18 في المشاريع التلاتة
- [ ] لسه: فتح الصفحات في المتصفح، order cancelled ما بيلغيش الكروت requested تلقائي

## طلب إضافي 3 (2026-11-01)
"انا عايز فى التطبيق او الموقع العميل لو طلب كارتين مثلا فيزيكال لما يتوافق عليه يظهرو عنده الكارتين سواء متكررين الرقم او لو الرقم مختلف بحيث طلب كارت فيزيكال يكون ظاهر عنده نسخه سوفت"
- القرار: لما كارت فيزيكال يبقى active (موافقة الأدمن) يتولد له تلقائي نسخة سوفت (parent_card_id) بنفس الرقم؛ العميل يشوف كل كروته (فعّالة + مطلوبة) في التطبيق والموقع من GET /mobile/my-cards.
- [x] backend: parent_card_id + observer + endpoint + test
- [x] website: panel في /account/physical-cards أو account
- [x] app ../deilar-mobile-new (PhysicalCardOrder 'My cards', vitest نجح): عرض الكروت

## تنفيذ المتبقي (2026-11-01)
- [x] إلغاء الطلب/فشله يقفل الكروت requested (Order::booted) + اختبار
- [x] IP العميل من الموقع (buyer_ip)
- [x] ترجمات admin t.member.* عربي/إنجليزي
- [x] AGENTS.md للتطبيق بند 18
- [x] vite build للباكند نجح (لا أخطاء Vue)
- [ ] مراجعة بصرية بالمتصفح (لم تتم)
