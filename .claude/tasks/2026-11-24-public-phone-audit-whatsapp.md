# Public phone numbers audit -> WhatsApp only

## المطلوب (حرفياً)
"اناعايز اشوف اي رقم تليفون معروض للعامه سواء فى لموقع اى التطبيق او فى الكارت السوفت او الفيزيكال لننا هنستخدم الواتس فقط فا يبقى موجود ايقونه واوتس ادوس عليها افتح الواتس وابتدى الكلام"

## القرارات/القيود
- الخطوة الحالية: جرد (عرض) كل مكان بيظهر فيه رقم تليفون للعامة: الموقع (../deilar)، التطبيق (../deilar-mobile-new)، الكارت السوفت، الكارت الفيزيكال، والباك إند.
- الهدف بعد الجرد: استبدال أي tel:/رقم ظاهر بأيقونة واتساب بتفتح wa.me. ما يتغيرش حاجة قبل موافقة المستخدم على الجرد.

## PROGRESS
- [~] جرد الأماكن
- [ ] عرض القائمة على المستخدم وأخذ الموافقة
- [ ] التنفيذ (بعد الموافقة)

## نتيجة الجرد (2026-11-24) — لسه ما اتغيرش أي كود
### الموقع الجديد ../deilar (tel:)
contact.tsx:51 | privacy.tsx:310 | facility.tsx:1860 (رقم عرض) | marketing/lib/branch-popup.ts:105 | contact-dock.tsx:188 | site-footer.tsx:151 | card-expired-banner.tsx:93,139 | legal-document.tsx:224 | branch-phones.tsx:38
### التطبيق ../deilar-mobile-new (tel:)
AccessModal:672 | MapView:699,790 | FloatingContactButton:59 | FamilyCircle:152 | ProfileView:1079,1092 | OfficialDeilarCard:263,271 (01020709993 ثابت) | StoreMapView:457,521 | SubscribePanel:131 | CardStatusBanner:67,99 | PayPage:121 | BranchList:111 | StoreDetailView:240,479 | AccountGate:34 | ProviderDetailView:151 | utils/downloadCard.ts:180 (نص فيه 01020709993)
### الباك إند (موقع Inertia العام) Pages/Guest
FacilityShowPage:163 | ContactPage:56 (+ whatsapp fallback 01156385251) | CardStatusBanner:31,48 | FloatingPartners:55 | GuestFooter:68 | OffersCarousel:190,252,332 | GuestPhoneLinks:7,49 | FloatingContact:62,247 | lang/{ar,en}/home.php:130
### الكارت
- السوفت والفيزيكال: حقل phone بيتطبع من card_templates.sample_data.phone = 01020709993 في القوالب 1,2 (فيزيكال) و4,5 (سوفت). ممكن تتغير من محرر القالب. ظهر الكارت: مفيش رقم في back_settings.
- ملحوظة: رقم مطبوع على الكارت الفيزيكال لا يتحول لأيقونة — لازم يتحول لـ QR/نص واتساب.
### مصدر الأرقام
ShopContact (/admin/setting): contact_phone, contact_phone_international, contact_whatsapp (فيه مفتاح واتساب جاهز).
### أرقام فروع ومرافق (من الداتا): branch.phone / facility phones / offer.phone / relatives phones

## PROGRESS
- [x] جرد الأماكن
- [ ] عرض القائمة على المستخدم وأخذ الموافقة على القرارات
- [ ] التنفيذ

## قرار المستخدم (2026-11-24)
"انا كلامى فقط على تليفون الموقع بتاعنا احنا 01020709993 الرقم ده ميظهرش ومكانه ايقونه وانس"
- النطاق: رقم الشركة 01020709993 فقط (موقع، تطبيق، باك إند عام، الكارت). أرقام الفروع/المرافق/العروض/الأقارب ما تتلمسش. رقم 01156385251 (home.php) ما اتذكرش — أنبه عليه بس.
- الرقم ما يظهرش كنص، مكانه أيقونة واتساب بتفتح wa.me من contact_whatsapp.
## PROGRESS (تنفيذ)
- [x] ../deilar (موقع): contact, privacy, legal-document, site-footer, contact-dock (واتساب بس), card-expired-banner — tsc+eslint نضاف
- [x] ../deilar-mobile-new (تطبيق): useContact بيرجّع phone/dial = null، شيلت أزرار الاتصال/الرقم من ProfileView, SubscribePanel, PayPage, CardStatusBanner, AccessModal, FloatingContactButton, AccountGate, OfficialDeilarCard (أيقونة واتساب), downloadCard.ts — tsc نضيف
- [x] ibusiness_medical Pages/Guest/CardStatusBanner.vue (واتساب بس). ContactPage/GuestFooter/FloatingContact/OffersCarousel/lang home.php فيها رقم تاني 01156385251 — ما اتلمسوش (مش رقم 01020709993) — سؤال مفتوح للمستخدم
- [ ] قوالب الكارت (sample_data.phone=01020709993، القوالب 1,2,4,5): ما اتلمسش — الأيقونة مطبوعة في تصميم الكارت، محتاج قرار + backup. سؤال مفتوح للمستخدم
