<?php

namespace App\Support;

use App\Enums\Contact\ContactSourceEnum;
use App\Models\ContactMessage;
use App\Models\MembershipSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Work waiting for the admin that nobody asked about: relatives a member added
 * that no admin has approved, payments to check, and members asking to be
 * called to add relatives. Shown as a banner across the admin and as badges on
 * the sidebar, so a pending item is seen without opening the right page.
 *
 * Counted for a minute at a time — these are queue lengths, not ledgers.
 */
final class AdminAlerts
{
    /**
     * @return array{pending_relatives: int, pending_subscriptions: int, relative_requests: int}
     */
    public static function counts(): array
    {
        return Cache::remember('admin-alerts.counts', 60, fn () => [
            'pending_relatives' => \App\Models\Membership::query()->relatives()->where('approval_status', 'pending')->count(),
            'pending_subscriptions' => MembershipSubscription::query()
                ->where('status', MembershipSubscription::STATUS_PENDING)
                ->where('method', '!=', MembershipSubscription::METHOD_PAYMOB)
                ->count(),
            'relative_requests' => ContactMessage::query()
                ->where('source', ContactSourceEnum::RELATIVE_REQUEST->value)
                ->where('status', 'new')
                ->count(),
        ]);
    }

    public static function forget(): void
    {
        Cache::forget('admin-alerts.counts');
    }

    /**
     * The banners this admin should see, empty when they cannot act on any.
     *
     * @return list<array{key: string, count: int, message: string, url: string}>
     */
    public static function for(?User $user): array
    {
        if (! $user || ! $user->can('manage memberships')) {
            return [];
        }

        $counts = self::counts();
        $ar = app()->getLocale() === 'ar';
        $alerts = [];

        if ($counts['pending_relatives'] > 0) {
            $alerts[] = [
                'key' => 'pending_relatives',
                'count' => $counts['pending_relatives'],
                'message' => $ar
                    ? "{$counts['pending_relatives']} قريب أضافه الأعضاء في انتظار الموافقة."
                    : "{$counts['pending_relatives']} relative(s) added by members are waiting for approval.",
                'url' => route('admin.user.membership.list', ['pending_relatives' => 1]),
            ];
        }

        if ($counts['pending_subscriptions'] > 0) {
            $alerts[] = [
                'key' => 'pending_subscriptions',
                'count' => $counts['pending_subscriptions'],
                'message' => $ar
                    ? "{$counts['pending_subscriptions']} دفعة اشتراك تنتظر المراجعة (إيصال أو تحصيل نقدي)."
                    : "{$counts['pending_subscriptions']} membership payment(s) are waiting to be checked (receipt or cash).",
                'url' => route('admin.subscription.index'),
            ];
        }

        if ($counts['relative_requests'] > 0) {
            $alerts[] = [
                'key' => 'relative_requests',
                'count' => $counts['relative_requests'],
                'message' => $ar
                    ? "{$counts['relative_requests']} عضو تجريبي يطلب مكالمة لإضافة أقاربه."
                    : "{$counts['relative_requests']} trial member(s) asked to be called to add relatives.",
                'url' => route('admin.contact-messages.index', ['source' => ContactSourceEnum::RELATIVE_REQUEST->value]),
            ];
        }

        return $alerts;
    }
}
