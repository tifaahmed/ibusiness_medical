# Any file input: accept ANY size; images compressed to ~100 KB

## المطلوب (حرفياً)
"انا عايز انك تقدر ترفع اى حجم لاى ملف فى اى خانه ادخال ملفات ولكن لو صوره اعملها ضغط عشان توصل لحجم 100 كيلوبايت"

## القرارات
- Supersedes 2026-11-14-admin-image-compress-1mb.md (limit 1 MB -> 100 KB, admin-only -> every upload route).
- No crop; keep aspect ratio; re-encode, shrink pixels last. Non-images untouched, any size.
- Remove size caps (`max:N`) on file/image validation rules; keep type (mimes) rules. Raise media-library max_file_size.

## PROGRESS
- [x] ImageShrinker LIMIT 100_000, quality steps down to 42
- [x] middleware on web + api groups (bootstrap/app.php)
- [x] size caps removed from 50 request/controller files (types kept)
- [x] config/media-library.php max 4 GB; Vue size checks + labels removed
- [x] ImageShrinkerTest 4 pass (no DB). [ ] browser check not done. [ ] production: php.ini upload_max_filesize/post_max_size + nginx client_max_body_size must be raised, then php artisan config:cache
