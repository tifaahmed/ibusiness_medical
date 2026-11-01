# Relative membership: name only

## المطلوب (حرفياً)
"انا عايز ان اثناء ادخال العضويه نوع الاقارب خليني اقدر ادخلها اسم بس وباقى الخانات مش مطلوبه. الكلام ده فى كل حته تبع العضويه نوع الااقارب"

## قرارات
- إلغاء شرط "واحد على الأقل من phone/DOB/national_id" في كل مكان (model guard, CreateRelative, UpdateRelative, Admin requests, API, Vue forms, AGENTS.md contract item 7 + tests).
- شرط الاسم الكامل لغير الابن/البنت يفضل زي ما هو (لم يُطلب تغييره).

## PROGRESS
- [x] backend guard/actions/requests
- [x] Vue forms
- [~] tests: run on ibusiness_medical_test with APP_CONFIG_CACHE=/tmp/nocache.php DB_DATABASE=ibusiness_medical_test php artisan test tests/Feature/{RelativesAsMembersTest,MemberFamilyTest,MemberRelativeEditTest}.php
- [x] AGENTS.md contract text (all 3 projects)
