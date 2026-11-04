# Put the Google Play link in .env as the Deilar app link

## المطلوب (حرفياً)
https://play.google.com/store/apps/details?id=com.deilar.card فى ال env   حط ده كا رابط ديلار

## PROGRESS
- [x] key = MOBILE_STORE_URL (config/mobile_app.php, default was already this link); added explicitly to .env + .env.example
- [ ] on the server: add the same line to its .env, then php artisan config:cache
