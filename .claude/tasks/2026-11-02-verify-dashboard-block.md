# Verify "Block from the dashboard" really locks the account out (and kicks live sessions)

## المطلوب (حرفياً)
"لما بعمل لحساب Block from the dashboard  انت متاكد انه مش هيقدر يدخل تماما ولو فى حساب هوا داخل فيه بالفعل يخرج منه تلقائى"

## PROGRESS
- [x] فحص: كل الـ 619 route الإدارية (uri admin*/api/membership-number/member-field) عليها EnsureAdminAreaAccess (مفيش route إداري خارجه)؛ الـ middleware بيرفض + logout + invalidate؛ لحظة الـ block بيتمسح صفوف sessions (driver=database) وبيتغير remember_token
- [~] فجوات: (1) صفحة /login كانت بتسيبه يسجل دخول ثم 403 → أرفضه عند الدخول برسالة (2) الاختبار الموجود بيجرّب الـ middleware لوحده بس → اختبار HTTP حقيقي (جلسة حية، block يمسح الجلسات، توكن Sanctum، login)
- [x] ثغرة اتلقت وتصلحت: refusal بـ Sanctum token كان 500 (RequestGuard::logout غير موجودة) → الـ middleware بيستخدم guard('web') صراحة (لسه مرفوض قبل كده، بس بخطأ)
- [x] /login: المحظور بيترفض عند الدخول برسالة (AuthenticateLoginResponse)
- [x] DashboardBlockTest: 5 اختبارات نجحت (جلسة حية، مسح الجلسات + rotate remember_token، token، login)
- [ ] لم يُختبر: متصفح حقيقي؛ ملاحظة: الحظر خاص بلوحة التحكم فقط — توكن تطبيق الموبايل/الموقع (عضو) ما اتلغاش عمداً
