# Quiz: sharing the referral code refills the daily plays on all sections
## المطلوب (حرفياً)
لما اللعبه بلعبها انا ليا محاوله واحده يوميه انا عايز لو شاركت الرفيرال كود تتفع المحاولات تانى كلها على كل الاقسام ولو لعبت عادى جدا نقاط الولاء توصلنى عادى جدا
## القرارات
- backend owns it: a verified-by-tap "I shared my referral code" (POST /referral/shared) gives +1 play on EVERY section for today, at most `quiz_share_refills_per_day` times a day (admin setting, default 1; 0 = off) — a share cannot be verified, so the cap stops farming. Points for those extra plays are booked normally (daily cap on points allows plays_per_day + refills).
- app: adds today's bonus to each section's play cap; offline-safe.
## PROGRESS
- [~] backend
- [x] backend DONE: migration 2026_11_19_150000 RUN (backup pre-quiz-share-*.sql), QuizShareRefills, POST /api/v1/referral/shared, upload cap = plays_per_day + today's refills, GET /loyalty + upload reply carry plays_per_day/share_bonus_plays_today/share_refills_left_today, setting quiz_share_refills_per_day (default 1, 0=off). Verified in rolled-back tx: play1 booked, play2 over limit, share -> play2 booked, 2nd share granted:false.
- [~] app fork
## توضيح من المستخدم (حرفياً)
للعلم سواء دفع او لا مجرد انه حمل التطبيق واتسجل انه تبعه  المحاولاه تتفتح
- decision: ALSO grant the referrer a refill when someone is registered under their code (referral claim accepted) — no payment needed; one per referral (unique), extra plays on every section that day; cap quiz_referral_refills_per_day (default 5, 0 = unlimited? no: 0 = off) against fake accounts. The share-tap refill stays (1/day).
- [x] referral registration refill DONE: migration 2026_11_19_160000 RUN (backup pre-quiz-referral-refill-*.sql), QuizShareRefills::forReferral hooked in Referrals::claim, setting quiz_referral_refills_per_day (5). Verified in tx: 1 referral +1, same referral idempotent, cap 5 holds, share cap separate. App needs no change (reads share_bonus_plays_today from the server).
