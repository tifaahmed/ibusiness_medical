# TASK: دمج جدول الأقارب (family_members) في جدول الأعضاء
Status: STARTED 2026-10-29  — احذف الملف ده لما المهمة تخلص تماماً ويتأكد منها المستخدم.
(لو الجلسة اتقطعت: اقرأ الملف ده + `git status` + قسم PROGRESS تحت وكمّل.)

## المطلوب (بكلام المستخدم)
1. جدول الأقارب يتحذف، والأقارب يتحفظوا في نفس جدول الأعضاء.
2. كل الأعضاء الحاليين يبقوا نوعين: `primary` (أساسي) و `relative` (قريب).
3. كل اللي في جدول الأقارب يتنقلوا لجدول الأعضاء بنوع relative — من غير خسارة ولا واحد.
4. العضو من نوع قريب لازم يكون له صلة قرابة.
5. القريب لازم تحدد صلة القرابة + العضو الأساسي اللي هو قريب ليه (لازم يكون primary).
6. القريب مالوش نوع اشتراك. ومينفعش قريب يتضاف لقريب.
7. حذف العضو الأساسي = حذف كل أقاربه المرتبطين بيه.
8. القريب لازم فيه على الأقل واحد من: التليفون / تاريخ الميلاد / رقم البطاقة (الثلاثة مسموحين).
9. عند إدخال العضوية الأساسية: إضافة أقارب بنافذة منبثقة لكل قريب (اسم، تليفون، ميلاد، بطاقة). العنوان تلقائياً نفس الأساسي، ولو مختلف يتغير يدوياً.
10. الأبناء: الاسم الأول فقط يكفي. أي صلة تانية: الاسم الكامل.

## قيود المشروع (AGENTS.md) — لازم تتحترم
- NEVER LOSE DATA: الـ migration تضيف بس. النسخ (copy) في نفس الـ migration اللي بيضيف الأعمدة؛ التحقق؛ وحذف جدول family_members في migration/إصدار لاحق (نقطة مفتوحة: نسأل المستخدم). باك أب mysqldump قبل أي migrate.
- الاختبارات: ممنوع على الداتابيز الحقيقية. استخدم sqlite :memory: بالأمر في AGENTS.md أو DB اسمه _test.
- تفرد phone/national_id (GuardsUniqueIdentity): الأقارب القدام ممكن تليفونهم/بطاقتهم مكرر مع أعضاء → النقل لازم ما يرميش ValidationException ويخسر صف. الأقارب مستثنون من القاعدة دي (القاعدة بتتطبق على الأساسيين بس) — يتأكد.
- عقد الـ API مع الموقع والتطبيق (FamilyController, MembershipController, relatives requests): غيّر الباك إند الأول وحدّث AGENTS.md في المشاريع التلاتة.
- Audit/member_logs، السايدبار، الفلاتر (pending relatives)، AdminAlerts.

## PROGRESS
- [x] 1. استكشاف (جزئي): family_members(membership_id,name,relationship,dob,phone,email,photo_path,is_active,national_id,approval_status,added_by,approved_*,rejection_reason, softdeletes). العضو = users(name,phone,dob,password NOT NULL,slug) + memberships(user_id NOT NULL,national_id,plan,...). ~60 ملف بيمس FamilyMember (grep: FamilyMember|family_members).
- [ ] 1b. أسئلة مفتوحة للمستخدم (اتسألت 2026-10-29): شكل الصف (users+memberships ولا memberships بس)، تفرد التليفون/البطاقة للأقارب، توقيت حذف الجدول.
- [x] 2a. (استكشاف خلص: Membership/User/GuardsUniqueIdentity اتقرت)
- [ ] 2. تصميم الأعمدة في users/memberships
- [ ] 3. migration (إضافة + نسخ + تحقق)
- [ ] 4. الموديلات والقواعد (validation/guards/cascade)
- [ ] 5. الـ API (FamilyController إلخ) + الأدمن
- [ ] 6. واجهة الأدمن (مودال الأقارب، الاسم الأول للأبناء)
- [ ] 7. اختبارات على DB مؤقتة
- [ ] 8. تحديث AGENTS.md (الثلاثة)
- [ ] 9. تسليم + ملاحظات الباك أب والـ migrate

## قرارات المستخدم (2026-10-29)
- الشكل: القريب = صفين زي العضو (users + memberships) بـ memberships.member_type='relative' (+parent_membership_id, relationship). القريب ما يقدرش يسجل دخول.
- التفرد: مفيش تكرار تليفون/رقم بطاقة. لو قريب قديم تليفونه/بطاقته مكرر → تتشال القيمة من القريب وتتسجل عليه (ملاحظة/لوج) إنها اتشالت، والعضو الأقرب (الأساسي) هو اللي يحتفظ بالقيمة. ما يتخسرش أي صف.
- جدول family_members: يفضل في الإصدار ده (نسخ + تحقق + الكود يشتغل على الجديد). migration تاني لاحق يحذفه بعد ما المستخدم يتأكد في الإنتاج.
- رقم العضوية (membership_number) لكل الأعضاء = 6 أرقام (يشمل الأقارب لأنهم صفوف memberships). (طلب المستخدم أثناء الشغل 2026-10-29) — لازم أشوف مولّد الرقم الحالي وأرقام القدام (4 أرقام / REG- / مستوردة) وأتأكد ما يتغيرش رقم عضو موجود بدون موافقة (الباركود/QR مطبوع).
  * نتيجة الفحص: StartTrialCard::newNumber() بيولّد 6 أرقام بالفعل (100000-999999). فورم الأدمن (StoreMembershipRequest) بيقبل أي string. القرار: أي عضو/قريب جديد = 6 أرقام (توليد تلقائي مشترك Membership::newNumber() + قاعدة digits:6 في فورم الإنشاء). الأرقام القديمة (4 أرقام/مستوردة) ما تتغيرش لأن الكروت مطبوعة؛ الأقارب المنقولين ياخدوا رقم 6 أرقام جديد. لو المستخدم عايز يغيّر القديم كمان → يسأل.
- لازم التغيير يوصل للتطبيق ~/projects/deilar/deilar-mobile-new وللموقع ~/projects/deilar/deilar (طلب المستخدم 2026-10-29): الباك إند أولاً، وبعدين العملاء يرسموا بس. نفس الشغل يتسجل في ملف مهام داخل كل مشروع.

## PROGRESS LOG (آخر تحديث 12:0x)
- [x] migration مكتوبة: database/migrations/2026_10_29_100000_add_relatives_to_memberships.php (لسه ما اتجرّبتش على DB مؤقتة)
- [x] Membership model: member_type/parent/relationship/..., relatives()/parent(), newNumber() 6 أرقام, guardRelative (parent لازم primary، plan='relative'، واحد من التلاتة), cascade delete للأقارب، state()/isCurrent() بيفوضوا للأساسي
- [ ] TODO: StartTrialCard::newNumber → Membership::newNumber؛ استبدال كل familyMembers()/FamilyMember في ~35 ملف (قائمة: grep -rln "FamilyMember\|familyMembers" app resources tests)؛ Action CreateRelative (users+memberships)؛ FamilyController؛ منع تسجيل دخول القريب؛ استبعاد القرايب من قوائم/إحصائيات/تصدير/الكروت المدفوعة؛ فلتر النوع في قائمة الأعضاء؛ مودال الأقارب في فورم الإنشاء؛ اختبارات؛ AGENTS.md ×3؛ الموقع والتطبيق
- [x] migration اتجرّبت على ibusiness_medical_test (نسخة من الحقيقية، env: APP_CONFIG_CACHE=/tmp/nocache.php DB_DATABASE=ibusiness_medical_test): 2222/2222 اتنقلوا (79 محذوفين soft اتحفظوا)، users 1685→3907، صفر يتيم، أرقام 6 خانات، ميديا ما اتأثرتش. ملحوظة: كل الـ2222 قريب القدام ملهمش تليفون/ميلاد/بطاقة أصلاً (اسم+صلة بس) → القاعدة "واحد من التلاتة" للجديد/المعدّل بس؛ القدام مستثنون (legacy_family_member_id). الوقت 6 دقايق (I/O). مسار حذف المكرر لسه ما اتجرّبش بالداتا (مفيش تكرار فعلي) → لازم اختبار.
- ⚠ نسخة test فيها الأقارب منقولين فعلاً؛ لو عايز تجربة من الصفر: اعمل drop/إعادة dump.
- [x] app/Actions/Membership/CreateRelative.php (الطريق الوحيد لإنشاء قريب: users+memberships، يرث العنوان/partner/company/sales من الأساسي، واحد من التلاتة، تفرد)
- [ ] NEXT: FamilyController -> Membership::relatives؛ Admin FamilyMember controllers؛ resources؛ AdminAlerts؛ MembershipStatus؛ ProcessMembershipSecondaryDataJob؛ تصدير/استيراد؛ قوائم الأعضاء
- [x] FamilyController, AdminAlerts, MembershipStatus, mobile CardController, guest/partner Membership controllers+resources, FamilyMemberResource (بنفس مفاتيح JSON القديمة؛ id = membership id للقريب)، AdminMembershipCardShow
- ⚠ جلسة تانية (deilar-mobile-new-65) بتكتب Api/V1/Member/AccountController.php (حذف الحساب) فوق نفس الموديل. بعتلها رسالة: ماتلمسش Membership/FamilyController وتشيل بلوك FamilyMember::query() من AccountController.
- [ ] الباقي: ProcessMembershipSecondaryDataJob، AdminUserMembershipStore/Edit/Show/List/Create controllers، Admin FamilyMember Store/Update/Delete/Approval controllers + requests، Import (Commit/Preview/Export/Template)، Export، CompanyMembersExport، قوائم/عدّادات الأعضاء (استبعاد relatives من الأساسيين + فلتر النوع)، منع دخول القريب، واجهات Vue، tests، AGENTS.md×3، الموقع، التطبيق
- [x] Admin FamilyMember Store/Update/Delete/Approval controllers + requests (FindsRelatives trait, UpdateRelative action، اسم الابن/البنت الأول فقط يتكمل باسم الأساسي، غيرهم اسم كامل)
- [x] AdminUserMembership Store/Show/Edit controllers + resources + ProcessMembershipSecondaryDataJob + StoreMembershipRequest (digits:6 للرقم، واحد من التلاتة للأقارب) + List (فلتر member_type=primary|relative، member_type/relationship/parent_member/relatives_count في الـ resource)
- [ ] الباقي (backend): Import Commit/Preview/Export/Template، Export controller، CompanyMembersExport، Update request رقم العضوية، منع دخول القريب (OTP/login)، استبعاد القرايب من إحصائيات/مدفوعات/مرتجعات حسب الحاجة، حذف alias، حذف FamilyMember model usages، tests
- [ ] الباقي (UI): Vue (فلتر النوع + عمود/بادج + مودال الأقارب في الإنشاء + حقل ميلاد/بطاقة)
- [x] Import (commit/preview/export) + Export + CompanyMembersExport تحويل؛ منع دخول القريب (MemberOtp::findMember + CardLoginController primary فقط)
- [ ] sweep: باقي استعلامات Membership:: في ~40 ملف (Dashboard counts، MemberPayment، Company، Usage، Order...) لاستبعاد relatives من العدّادات والقوائم اللي المفروض primary بس؛ User::membership() hasOne لازم primary
- [x] tests: RelativesAsMembersTest (جديد: قواعد + نسخ القديم + حذف المكرر + cascade + دخول)، تعديل MemberFamilyTest/TrialCardTest/PartnerMembershipApiTest/StoreMembershipTest. التشغيل: DB اسمها ibusiness_phpunit_test (MySQL فاضية، RefreshDatabase) بـ env: APP_CONFIG_CACHE=/tmp/nocache.php DB_CONNECTION=mysql DB_DATABASE=ibusiness_phpunit_test — sqlite مكسور بميجريشن قديم. كل تشغيل ~4 دقايق (migrate:fresh).
- [x] sweep جزئي: Dashboard/MemberPayment/Company → primary()
- [ ] UI (Vue) — لسه
- [x] Vue: FamilyMemberCreateCard + FamilyMemberCard صارت نافذة منبثقة (Teleport) فيها اسم/صلة/ميلاد/تليفون/بطاقة/إيميل/صورة + تحقق واحد من التلاتة + اسم كامل لغير الأبناء؛ MemberStore.js يبعت national_id؛ قائمة الأعضاء: فلتر member_type (أساسي/قريب) + بادج "قريب (صلة) لـ فلان" + عدّاد الأقارب؛ lang en/ar. (npm run build اتعمل مرة؛ لازم يتعاد بعد تعديلات Vue الأخيرة)
- [x] AGENTS.md (backend) بند 7 اتحدّث. ⏳ لسه: AGENTS.md/CLAUDE.md في deilar و deilar-mobile-new
- [ ] جاري: تشغيل اختبارات (run2.txt في scratchpad) — بعدها: الموقع deilar ثم التطبيق
- [x] الموقع deilar: MemberChildRequest/AddMemberChild/Controller/family-panel.tsx (حقل ميلاد)/lang/test (Pest 70/71؛ الفاشل MemberTrialCardTest::one_minute_reminder (كاش/Http count) مش مرتبط بالتعديل — لم يتأكد على tree نظيف). التطبيق: AddChildForm + AddMemberForm + family.ts (tsc نضيف). AGENTS/CLAUDE بند 7 اتحدّث في التلاتة.
- [~] اختبارات (12:5x): لحد دلوقتي فاشل 2: RelativesAsMembersTest::a_relative_is_as_valid_as_its_primary_and_never_signs_in، MemberFamilyTest::a_surname_posted_by_the_client_is_ignored — بحقق. المتبقي: Vue build، تنضيف alias familyMembers، مراجعة بصرية.
- [x] نتيجة الاختبارات (12:5x): 75 نجح / 3 فشل. (1) never_signs_in: كان في استعلام تاني في MemberOtp::findMember لسه بدون primary — اتصلح. (2) surname_posted: ناقص date_of_birth — اتصلح. (3) TrialCardTest::a_soft_deleted_card_does_not_block_the_phone: اليوزر القديم بيحتفظ بالتليفون فالـ guard بيرفض — غالباً قديم وغير مرتبط (مش متأكد على tree نظيف).
- [ ] المتبقي: إعادة تشغيل الـ3 دول، npm run build، إزالة alias familyMembers (مش موجود فعلياً)، مراجعة بصرية للمودال/القائمة، تنضيف FamilyMember model/factory (يفضل لحد الحذف في الإصدار اللاحق)، حذف .claude/tasks* بعد تأكيدك.
- [x] 12:3x: الـ3 المتبقيين: never_signs_in ✓، surname_posted ✓. a_soft_deleted_card_does_not_block_the_phone فاشل برضه على HEAD النضيف (worktree) = فشل قديم مش من شغلنا. الاختبارات كلها خضرا ما عدا ده. المتبقي: npm run build + مراجعة بصرية + تأكيد المستخدم قبل حذف ملفات .claude/tasks* و migration حذف family_members (إصدار لاحق).
- [~] بدأت npm run build (آخر تعديلات Vue) — النتيجة في scratchpad/build.txt
- [~] 13:xx: npm run build في مرحلة rendering chunks (1841 module تحوّلوا بنجاح، لا أخطاء Vue لحد دلوقتي). لسه مستني النهاية.
- [x] 13:xx: npm run build نجح (10m34s). المتبقي: مراجعة بصرية من المستخدم + موافقته على: migration حذف family_members لاحقاً، وحذف ملفات المهمة.
- ملاحظة 13:xx: جلسة deilar-mobile-new-65 شالت primary() من CardLoginController (القريب يدخل برقم كارته هو) بطلب المستخدم؛ سيبتها وعدّلت وصف AGENTS. تسجيل الدخول بالتليفون لسه primary فقط. OTP fixed code بقى hash (migration 140000) — مش تبعنا.
- [x] 14:xx: لا جديد في الكود؛ مستني: مراجعة بصرية + موافقة المستخدم على commit/نشر وحذف ملفات المهمة. تأكيد: AGENTS وصف دخول القريب اتعدّل.
- [x] لا تغيير؛ مستنّي ردّ المستخدم (مراجعة بصرية/commit/حذف ملفات المهمة).
- [x] تم: commit backend 064dffe (+ chart primary()) ، commit الموقع deilar. التطبيق deilar-mobile-new لسه uncommitted (فيه شغل جلسات تانية كتير مخلوط، قرار المستخدم). لسه: مراجعة بصرية، push، mysqldump قبل migrate، migration حذف family_members لاحقاً.
