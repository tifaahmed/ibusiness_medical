# Admin notifications: sound + bell in header for new orders
## المطلوب (حرفياً)
يهمنى جدا ان اى طلب يجي الموقع يسمع مع اشعار فى لوحه التحكم وجه يكون عباره عن ايقونه فى الهيدر لما ادوس عليها تنزل قايمه بالاشعاراتت ولما ادوس على واحده توديني للمكان المطلوب انى اشوفوه
## PROGRESS
- [~] explore AppLayout header, AdminAlerts, order creation points, broadcasting?
- [x] backend: migration 2026_11_19_130000 RUN (backup pre-admin-notifications-*.sql); AdminNotification model, Order::created hook (all 3 order creators); AdminNotificationController index/read; routes admin.notifications.index/read (perm: manage orders|manage own orders|view orders)
- [ ] NEXT: NotificationBell.vue in AppHeader (poll 20s, badge, dropdown, click->read+visit, WebAudio sound), verify
- [x] NotificationBell.vue in AppHeader (poll 20s, badge chip-negative, dropdown, click -> mark read + router.visit(url), WebAudio chime armed by first click, title "(n)")
- [ ] verify: e2e write via order creation in tx + endpoint JSON; browser check by user (vite dev server running, I did not start another)
- [x] e2e verified in rolled-back tx (order -> notification -> endpoint -> read -> unread 0); AGENTS.md section; tests/Feature/AdminNotificationTest.php (run on ibusiness_phpunit_test, result pending)
