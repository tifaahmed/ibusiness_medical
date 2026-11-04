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
