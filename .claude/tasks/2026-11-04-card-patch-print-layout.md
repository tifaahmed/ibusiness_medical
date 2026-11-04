# Card batch print: margins top/bottom, pin/hole marker area, tighter gaps, back side aligned

## المطلوب (حرفياً)
admin/membership-card-patches هنا انا عايز لما اطلع الكلوت  فى ملف عشان اطبعها انا عايز الكروت تبعد عنطرف الورقه فوق وتحت  وحط شكل فوق عشان هحد فيه دبوس يمسك الورقه بس المكان يكون بعسد عن مستطيلات الكورت بحيث قلل المسافات بين الكروت والمقابل حطها فوق وتحت ونفس الوقت فى ظهر الكارت عشان الاتنين يكونو فى فس المكان اثناء قص الورقه

## القرارات
- Reading: cards must sit away from the sheet's top & bottom edges; a pin/staple marker shape at the TOP, outside the card rectangles; gaps BETWEEN cards smaller (the saved space becomes the top/bottom margin); the BACK page uses the identical layout so fronts and backs line up when cut.

## PROGRESS
- [x] code: resources/js/Pages/Admin/MembershipCard/_components/PdfBuilder.js (sheetLayout, buildBackPdf, buildBatchAndZip)
- [x] GAP_X 0.5->0.2, GAP_Y 0.3->0.12; TOP_RESERVE 1.0 + BOTTOM_RESERVE 0.5 shared with spare; pin mark (circle+cross) centered in the top strip, drawn on every page front and back (same spot)
- [ ] numeric check of the layout (node) + vite build; real print test by the user
- [x] numeric check (node): 2x5=10/page as before; top 1.11cm, bottom 0.61cm (were 0.5/0.5), pin center 0.555cm from the edge, 0.355cm clear of edge and of first card; gaps X 0.2 Y 0.12
- [x] vite build OK. NOT verified by an actual print/PDF yet.

## 2026-11-04 — طلب إضافي (حرفياً)
عايز خط فى نص الصفحه عشان لما بقطع الورقه بالنص سواء وش او ظهر
- فهمي: خط رأسي في منتصف الورقة (x = 10.5 سم) يقع في الفراغ بين عمودي الكروت، على الوش والظهر.

## 2026-11-04 — طلب إضافي (حرفياً)
وعايز خط رفيع خفيف بين الكروت عشان اخد بالى وانا بقطع
- فهمي: خطوط رفيعة فاتحة في الفراغ بين الكروت (أفقية بين كل صف والتاني + الخط الرأسي اللي في النص بين العمودين)، وش وضهر، ومفيش خط بيعدي فوق أي كارت.
- [x] guides: vertical centre line (x=10.5, in the 0.2cm column gap) + a horizontal line in the middle of every row gap (0.06cm either side), light grey 200, 0.012cm, edge-to-edge in the gaps only; built OK; not printed yet

## 2026-11-04 — طلب إضافي (حرفياً)
عايز الكارت يكون فى نص المربع الخاص بيه مش عايزه قريب من اى خط عموما
- حساب: 5 صفوف × 5.5سم = 27.5 من 29.7 → ما يفضلش مكان لمسافة حقيقية حوالين الكارت (كان 0.06سم). عشان الكارت يبقى في نص مربعه بعيد عن الخطوط لازم الخلية تكبر → 4 صفوف = 8 كروت/صفحة بدل 10 (أفقياً 2 عمود زي ما هي). ده قرار تكلفة ورق: مذكور للمستخدم.
- التنفيذ: CELL_PAD ثابت واحد (0.5سم)، الخلية = الكارت + 2×PAD، الكارت في نص الخلية، الخطوط = حدود الخلايا (شبكة كاملة)، الضهر نفس الشبكة.
- [x] cell model done: CELL_PAD 0.5, cell = card+1cm each way, 2x4 = 8/page (was 10), grid top 2.1cm / bottom 1.6cm, pin at 1.05cm, card clear of every line by 0.5cm; vite build OK. (10/page impossible with real clearance: 5 rows leave ~0.07cm)

## 2026-11-04 — طلب إضافي (حرفياً)
admin/membership-card-patches  انا عايز قبل اى تحميل ل ملف حيب   عايز احدد الكلروت الى هرفعها عشان لو 11 كرت مصلا اقدر احدد 8 او 10 براحتى مش لازم كله
- فهمي: في صفحة الدفعة، قبل أي تحميل (PDF الدفعة / zip / ضهر...) أختار الكروت اللي هتتضمن (checkbox لكل كارت + تحديد الكل/إلغاء)، والملف يتولد من المختار بس.
- [ ] read Show.vue download buttons
- [x] Show.vue: checkbox per card + All/None/first N + counter 'N/M selected · sheets'; 3 back-PDF buttons + new 'selected cards (fronts)' PDF use only the selection; PdfBuilder mode 'fronts'; saved batch PDF stays whole
- [x] vite build OK (selection + cell layout). Not verified in a browser or by a real print.

## 2026-11-04 — قانون من المستخدم (حرفياً)
انا مهم عندى فى ورقه الكروت انها تاخد 10 فى الصفحه الواحده ده قانون
- القرار: 10 كروت/صفحة (2×5) ثابتة لحجم 8.5×5.5. تراجع عن 8/صفحة. الباقي (هامش فوق/تحت، دايرة الدبوس، خطوط القطع، توسيط الكارت في مربعه) ياخد المتبقي: أفقياً مساحة كبيرة (0.5سم) ورأسياً قليلة بحكم A4 (≈0.1سم).
- [x] sheetLayout: PAD_X 0.5 ثابت، PAD_Y يتحسب من المتبقي (0.095)، TOP 0.85 / BOTTOM 0.4، pin r=0.15 → 2×5 = 10/صفحة (تأكدت بحساب Node لـ 8.5×5.5 و8.5×5.4 و8.6×5.4). لم أشغّل vite build (خادم dev المستخدم شغال، HMR يلتقط التعديل).

## 2026-11-04 — بلاغ (حرفياً)
الكارت قريب من الخط الى فوق وبعيد عن الخط الى تحت
- [ ] الحساب النظري متماثل (padY 0.095 فوق وتحت) → لازم أدور على عدم تماثل حقيقي: مقاس/نسبة الصورة المرسومة، أو رسم الخطوط، أو إزاحة في cardRenderer
- [x] فحص حقيقي: بنيت PDF بنفس كود sheetLayout/slotXY/drawPin (مستطيلات بدل صور الكروت)، حوّلته لـ PNG بـ ghostscript 150dpi وقست: كل كارت 0.085–0.102سم من الخط فوق و0.102 من تحت (فرق = بكسل واحد)، 10 كروت، متماثل. صور القوالب full-bleed (لا إطار أبيض). لم أقدر أعيد "قريب من فوق وبعيد من تحت".
- [ ] أنتظر من المستخدم: أي زرار/ملف (وش فقط، وش+ضهر، mirror، المحفوظ)، أي صف/صفحة، ولقطة شاشة. نسخة التجربة: ~/Downloads/cards-layout-test-10-per-page.pdf

## 2026-11-04 — طلب (حرفياً)
فى اخر الصفحه انا عايز الكروت توصل لاخر الصفحه وعايز مساحه محترمه بين الكارت واخوه وبن الخطوط
- القانون: 10/صفحة ثابتة. الحساب: BOTTOM_RESERVE 0 (الشبكة توصل لآخر الورقة)، TOP 0.85 (دبوس) → كل صف 5.77سم → فراغ رأسي 0.135 حوالين الكارت (0.27 بين كارتين)؛ أفقياً PAD_X 0.7 (1.4سم بين عمودين، 0.7 من الخط).
- تحذير للمستخدم: أي طابعة عادية بتقص ~0.3سم من الحافة → آخر صف ممكن يتقص؛ يحتاج طابعة بدون حدود/قص.
- [x] PAD_X 0.7, BOTTOM_RESERVE 0 → قياس PDF حقيقي (gs 150dpi): 10 كروت؛ آخر كارت 0.14سم من آخر الورقة؛ فراغ رأسي بين كارتين 0.25سم، كارت→خط 0.135؛ أفقياً 1.41سم بين العمودين، كارت→خط 0.7؛ نسخة تجربة ~/Downloads/cards-layout-test-10-per-page-v2.pdf. لم أشغّل vite build (dev server المستخدم شغال).

## 2026-11-04 — طلب (حرفياً)
انا عايز صف الكرت الى على اليمين يكون اقصي اليمين والى على لاشمال يكون اقصي الشكال
- القرار: عمود الشمال ملزوق في أقصى الشمال وعمود اليمين في أقصى اليمين (SIDE_MARGIN صغير ثابت)، والفراغ كله في النص حوالين خط القص (≈3.5سم). التماثل محفوظ فالضهر المقلوب يتطابق. الخطوط الأفقية من حافة لحافة، والرأسي في نص الفراغ.
- تحذير: الطابعات العادية مش بتطبع آخر ~0.3سم من الحافة يمين/شمال.
- [x] SIDE_MARGIN 0.25: عمود الشمال x=0.25، عمود اليمين يخلص عند 20.75، فراغ 3.51سم في النص حوالين خط القص (10.5)؛ الضهر المقلوب يتبادل العمودين بتماثل (0.25 ⇄ 12.25)؛ قست وشفت صورة PDF حقيقي (gs). نسخة تجربة ~/Downloads/cards-layout-test-10-per-page-v3.pdf. لم أشغّل vite build.

## 2026-11-04 — طلب (حرفياً)
انا عايز الخط الى فى النص يكون واصل من فوق لحت الاخر تحت
- [x] الخط الرأسي خط واحد من y=0 لـ y=29.7 يعدّي من نص دايرة الدبوس؛ قياس بالبكسل (150dpi): صفر صفوف فاضية في شريط 7px حوالين x=10.5 على كل الـ1754 صف. نسخة ~/Downloads/cards-layout-test-10-per-page-v4.pdf. لم أشغّل vite build.

## 2026-11-04 — طلب (حرفياً)
انا عايز الخط واصل حتى فوق علامه الدبوس
- [x] الكود الحالي (والمقدَّم من dev server — جلبته بـ curl) بيرسم الخط من y=0 لـ y=29.7 فوق الدبوس وتحته؛ قياس PDF: حبر الخط في 16/16 صف فوق الدبوس و18/18 على الدبوس و16/16 تحته. غالباً الملف اللي المستخدم شافه نسخة قديمة (v3 كان فيه فجوة) → يعيد التحميل. لا تغيير كود.
