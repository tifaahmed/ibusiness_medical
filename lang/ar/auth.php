<?php

return [
    // Shown when a signed-in account has no area it can be sent to (no admin
    // role and no membership page), so the session is ended and the visitor is
    // returned to the login screen.
    'no_landing_page' => 'لا توجد صفحة متاحة لحسابك بعد. برجاء تسجيل الدخول مرة أخرى أو التواصل مع الدعم.',

    /*
     * The storefront's phone login, answered to the Deilar site over the
     * key-gated partner API. Every one of these travels back as `message`
     * beside a machine-readable `reason`, so the partner can show its own
     * wording and fall back to these when it has none.
     */
    'otp' => [
        'invalid_phone' => 'هذا الرقم لا يبدو رقم هاتف صحيحاً.',
        'unknown_phone' => 'لا توجد عضوية مسجلة بهذا الرقم.',
        'cooldown' => 'تم إرسال رمز بالفعل. برجاء الانتظار قبل طلب رمز جديد.',
        'not_configured' => 'تسجيل الدخول بالهاتف متوقف حالياً.',
        'send_failed' => 'تعذر إرسال الرمز. برجاء المحاولة بعد قليل.',
        'sent' => 'تم إرسال رمز التحقق.',
        'sent_fixed' => 'أدخل رمز التحقق للمتابعة.',
        'invalid_code' => 'الرمز غير صحيح.',
        'expired_code' => 'انتهت صلاحية الرمز. برجاء طلب رمز جديد.',
        'too_many_attempts' => 'عدد محاولات كبير برموز غير صحيحة. برجاء طلب رمز جديد.',
        'verified' => 'تم تسجيل الدخول بنجاح.',
    ],
];
