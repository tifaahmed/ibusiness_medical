# Soft card templates (front + back), with expiry date + full name

## المطلوب (بكلام المستخدم حرفياً)
"انا عايز اعمل كارت تانى وش وظهر يظهر كا سوت مختلف عن كارت الفيزيكال admin/card-templates  انا عامل اتنين هنا محتاج اتنين كمان نفس الشكل فى كل حاجه بس الى هيذيد ان رقم انتهاء العضويه هحدد مكان ليه  واسم الشخص بالكامل"

## القيود
- AGENTS.md: إضافة فقط (أعمدة/جداول nullable)، mysqldump قبل migrate، لا حذف/استبدال للقوالب الموجودة، الاختبار على DB `_test`.
- ملاحظة بحث: الكارت الفيزيكال الحالي (وش + ضهر) ما يتغيرش.

## الخطة (من الفحص)
- الكارت اللي العميل بيشوفه (QR/الموقع/التطبيق) بيتولد في `CardGenerationService::generate()` من نفس قالبين (with_partner / no_partner) اللي بتتطبع منهم الفيزيكال.
- القرار: عمود `card_templates.kind` (physical|soft، الافتراضي physical، add-only). القوالب الحالية = physical ومش بتتغير.
- حقلين جداد في الـ layout للـ soft فقط: `full_name` (اسم العضو كامل) و `expiry_date` (تاريخ انتهاء العضوية، d/m/Y) — الأدمن يحدد مكانهم في محرر القالب.
- الـ soft يتولد في صف `membership_cards` منفصل (mode=soft) فما يلخبطش الفيزيكال. endpoints العميل (mode=full) بتتحول لـ soft لما يكون فيه قالب soft للعضو (مفيش تعديل في التطبيق/الموقع).
- الفيزيكال (CardGenerator/QuickAdd/المطبوعات) يقرا قوالب physical بس.
- الكاش: يتمسح لما يتغير القالب، تاريخ الانتهاء، أو اسم العضو.

## PROGRESS
- [x] فحص + خطة
- [x] migration `2026_11_17_130000_add_kind_to_card_templates` (add-only) بعد mysqldump (~/db-backups/..._before_soft_templates_*.sql، 93MB، متحقق)
- [x] CardTemplate (kind، scopes، softFor، hidden_fields)، LayoutDefaults (full_name، expiry_date)، Request
- [x] CardGenerationService (soft mode، الاسم والتاريخ، back، invalidate)، endpoints Guest/Member، hooks (Membership expiry، User name)، ProvidesCardTemplates physical فقط
- [x] محرر القالب (Kind select، إخفاء/إظهار الحقلين)، القائمة (chip)، cardRenderer
- [x] قالبين soft اتعملوا (#4 with_partner، #5 no_partner) من الفيزيكال؛ توليد فعلي لكارت العضو #6133: soft فيه الاسم وتاريخ الانتهاء، والفيزيكال زي ما هو
- [ ] أماكن الاسم/التاريخ الافتراضية تقريبية — الأدمن يحددها بالسحب من المحرر
- [ ] لم يتم فحص محرر القالب في المتصفح، ولا الـ back للـ soft (نسخة من ضهر الفيزيكال)، ولا اختبارات آلية (مفيش DB `_test`)
- [ ] لم يتم commit
