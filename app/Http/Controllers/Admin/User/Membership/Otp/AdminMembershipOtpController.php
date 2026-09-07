<?php

namespace App\Http\Controllers\Admin\User\Membership\Otp;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Setting;
use App\Models\User;
use App\Support\OtpSettings;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The storefront login's code policy, driven from the membership screen.
 *
 * Two levers, deliberately separate:
 *
 *   site()    turns SMS off for everybody and names the code that stands in
 *   member()  exempts one member, leaving the rest of the site alone
 *
 * They are guarded by different permissions because they are different sizes
 * of decision. Turning SMS off site-wide is a settings change — it weakens the
 * login for every member at once — so it wants `manage settings`, the same
 * permission that guards /admin/setting. Giving one member a fixed code is
 * membership administration, and sits with the permissions that already let an
 * admin edit that member.
 *
 * Both are logged. A login that stops asking for a real code is exactly the
 * kind of change somebody needs to be able to account for later.
 */
class AdminMembershipOtpController extends BaseController
{
    /**
     * Set the site-wide policy: SMS on, or off with a fixed code standing in.
     */
    public function site(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sms_enabled' => ['required', 'boolean'],
            /*
             * Digits only, and bounded by the same limits a generated code
             * obeys — a "fixed code" nobody can type into a 4-box form is not
             * a way in, it is a lockout. Nullable because clearing it is a
             * real choice: with SMS off and no fixed code, nobody signs in,
             * which OtpSettings::fixedCode() reports rather than papers over.
             */
            'fixed_code' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
        ], [
            'fixed_code.regex' => 'The fixed code must be 4 to 8 digits.',
        ]);

        $smsEnabled = (bool) $validated['sms_enabled'];
        $fixedCode = $validated['fixed_code'] ?? null;

        SiteSettings::put(
            OtpSettings::SMS_ENABLED,
            $smsEnabled,
            Setting::TYPE_BOOLEAN,
            ['en' => 'Send login codes by SMS', 'ar' => 'إرسال رمز الدخول برسالة'],
        );

        SiteSettings::put(
            OtpSettings::FIXED_CODE,
            $fixedCode ?? '',
            Setting::TYPE_STRING,
            ['en' => 'Fixed login code (when SMS is off)', 'ar' => 'رمز الدخول الثابت (عند إيقاف الرسائل)'],
        );

        Log::info('Storefront login policy changed', [
            'by' => $request->user()?->id,
            'sms_enabled' => $smsEnabled,
            'fixed_code_set' => $fixedCode !== null,
        ]);

        return back()->with('success', $smsEnabled
            ? 'Login codes are sent by SMS again.'
            : 'SMS is off — members sign in with the fixed code.');
    }

    /**
     * Give one member a fixed code, or clear it so they follow the site again.
     */
    public function member(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'fixed_code' => ['nullable', 'string', 'regex:/^\d{4,8}$/'],
        ], [
            'fixed_code.regex' => 'The fixed code must be 4 to 8 digits.',
        ]);

        $code = $validated['fixed_code'] ?? null;

        /*
         * An admin account has no membership, so it never resolves through
         * MemberOtp::findMember() and a code set on one would do nothing at
         * all. Refusing is better than storing something inert that looks
         * like it works.
         */
        if ($code !== null && ! $user->memberships()->exists()) {
            return back()->withErrors([
                'fixed_code' => 'That account has no membership, so it cannot use the storefront login.',
            ]);
        }

        $user->forceFill(['otp_fixed_code' => $code])->save();

        Log::info('Member login code changed', [
            'by' => $request->user()?->id,
            'member' => $user->id,
            'cleared' => $code === null,
        ]);

        return back()->with('success', $code === null
            ? $user->name.' follows the site login setting again.'
            : $user->name.' now signs in with a fixed code.');
    }
}
