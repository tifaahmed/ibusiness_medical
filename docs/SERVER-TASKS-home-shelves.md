# سليدرات الرئيسية في التطبيق — اللي لازم يتعمل على السيرفر

الرئيسية في التطبيق فيها 6 سليدرات (12 عنصر لكل واحد): أفضل المتاجر، أفضل العروض، أحدث المتاجر، أفضل الأماكن الطبية، أفضل العروض الطبية، أحدث مقدمي الخدمات الطبية.
التطبيق بيطلب `GET /api/v1/mobile/home-shelves` (طلب واحد). الرد بيتقرا من **كاش محفوظ** وما بيشغّلش أي استعلام قايمة. الكاش بيتبني من الأمر `home:shelves-refresh` كل يوم الساعة 03:00 (بتوقيت `app.timezone`).

## 1) بعد كل نشر (deploy) — مرة واحدة
```bash
cd /path/to/ibusiness_medical
php artisan migrate --force          # يضيف عمود visits_count على facilities و stores (إضافة بس، مفيش حذف)
php artisan home:shelves-refresh      # يبني الكاش دلوقتي (ar + en) من غير ما ينتظر 03:00
php artisan route:cache && php artisan config:cache   # لو الـ deploy بتعملهم
```
> الـ migration فيها نسخ أولي لعدد زيارات المنشآت من جدول `page_visits`؛ المتاجر بتبدأ من 0. خد نسخة احتياطية من القاعدة قبل `migrate`.

## 2) الجدولة اليومية (لازم تكون شغالة)
لازم cron واحد بيشغّل scheduler لارافيل كل دقيقة (لو موجود قبل كده للأمر `memberships:deactivate-expired` يبقى جاهز):
```cron
* * * * * cd /path/to/ibusiness_medical && php artisan schedule:run >> /dev/null 2>&1
```
المهمة اللي بتتضاف: `home:shelves-refresh` يومياً 03:00 (معرّفة في `routes/console.php`).

## 3) التحقق إنه شغال
```bash
# المهام المجدولة — لازم تلاقي home:shelves-refresh
php artisan schedule:list

# شغّل الأمر بإيدك — لازم يطلع: Home shelves rebuilt for [ar, en] in ~1-2s.
php artisan home:shelves-refresh

# الرد لازم يرجع 6 مفاتيح × 12 عنصر، وبسرعة (أقل من ~0.5 ث)
curl -s "https://card.deilar.com/api/v1/mobile/home-shelves?lang=ar" | python3 -c "import sys,json; d=json.load(sys.stdin); print({k:len(v) for k,v in d['shelves'].items()}, d['built_at'])"
```
- `built_at` لازم يكون تاريخ النهارده بعد 03:00 (لو لسه تاريخ امبارح يبقى الـ cron/scheduler مش شغال).
- لو الكاش اتمسح (`cache:clear`) أول طلب هيبنيه لوحده (حوالي ثانية)، والباقي من الكاش. بعد أي `cache:clear` شغّل `php artisan home:shelves-refresh`.
- الكاش بيتخزن في `CACHE_STORE` (حالياً `database`)، يعني بيفضل بعد إعادة التشغيل.

## 4) ملاحظات
- الترتيب «الأكتر زيارة»: عداد `visits_count` بيزيد +1 مع كل فتح لصفحة المنشأة أو المتجر في التطبيق. لما العدادات تتساوى (كلها 0 في الأول) الترتيب عشوائي وثابت طول اليوم.
- أي تعديل من الأدمن (متجر جديد، عرض) ما بيظهرش في السليدرات غير بعد التحديث اليومي، أو بتشغيل `php artisan home:shelves-refresh` بإيدك.
