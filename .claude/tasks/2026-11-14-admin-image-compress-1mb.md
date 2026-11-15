# Admin image uploads: accept any size, compress to <= 1 MB (no crop)

## المطلوب (حرفياً)
"انا عايز فى لوحه التحكم انى اقدر ارفع عليها اى حجم من الصور ولكن لما تدخل يحصلها ضغط عشان تتناسب مع الحجم الى يفترض يتسجل وبشكل اساسى انا عايز اعلى صوره على اللوحه لا تتجاوز ال1 ميجا. فا كل الصور نزل حجمها انه ميتعداش ال1 ميجا من غير قص مجرد ضغط بمعنى تخفيش جوده الصوره"

## القرارات
- No crop. Keep aspect ratio. Re-encode only; lower quality in small steps, shrink pixels ONLY as a last resort. Never enlarge. Images already <= 1 MB untouched.
- Existing images: never overwritten without backup (no-delete rule) -> separate command with --dry, originals copied first.

## PROGRESS
- [x] found: ~106 addMedia + ~15 ->store(); one middleware covers all (strips nothing, replaces UploadedFile before controllers/validation)
- [x] App\Services\Images\ImageShrinker + App\Http\Middleware\ShrinkUploadedImages on the admin route group (routes/web.php:331)
- [x] not needed: compressed files are < 1 MB so every existing max:5120/10240 rule passes. Server side: php.ini upload_max_filesize/post_max_size + nginx client_max_body_size must allow big originals (local: 512M/520M)
- [x] tests/Unit/ImageShrinkerTest.php 4 pass (no DB)
- [ ] browser check of a real upload on /admin (not done)
- [ ] command for existing images (--dry, backup) — NOT RUN
