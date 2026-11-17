# Admin member create: add as TRIAL card + per-member fixed OTP

## المطلوب (حرفياً)
"/admin/user/membership/create  يفترض انى اقدر اضيف حد هنا كا عضويه trial اظهرلى الخيار ده وكمان  حطلى انى اقدر احدد otp ثابت ليها"

## القرارات
- Per-user fixed OTP follows CLAUDE.md OTP rule: super admin only, hashed, audit log, optional expiry, visible warning while active.

## PROGRESS
- [x] explored
- [x] trial: StoreMembershipRequest is_trial + StoreUserMembershipAction (plan trial, expiry = trial days if empty) + checkbox on create
- [x] fixed OTP: fixed_code (+expires) super-admin only, hashed, MemberLog + Log::warning; field in MemberForm (create). [ ] NOT tested in browser/DB; needs npm run build

## طلب إضافي (حرفياً)
"admin/user/membership/create   لو هيا عضويه trial  عادى انى مدخلش العنوان ولا محافظه والمدينه ولا رقم البطاقه"
- [x] trial => national_id, governorate, city, address optional (Store+Update request, store controller skips empty address, Vue zod). [ ] not browser-tested, needs npm run build

## طلب إضافي (حرفياً)
"/admin/user/membership  Login code for أسماء ثابت — A fixed code is active for this member until 11/30/2026, 3:27:00 PM. It is stored hashed and cannot be read back; typing a new one replaces it. — انا عايز الخانات فيها التاريخ والرقم الى حطيته مش عايزه مخفى"
- Decision: keep hash for verification (global rule: fixed codes hashed) + ADD an encrypted copy (Crypt, new nullable column) shown ONLY to super admin; expiry date prefilled. Old codes (hash only) cannot be recovered.
- [x] code done: migration 2026_11_16_100000 (additive), User::setFixedLoginCode/fixedLoginCodeValue, list resource field (super admin only), dialog prefills code+date. [ ] migration NOT run yet (back up first: mysqldump, then php artisan migrate --force); [ ] npm run build; [ ] not browser-tested
