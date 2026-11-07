<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralReward;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who brought whom: every referral, whether the new member has paid yet, and what the referrer was paid (a trial reward, then a commission on every paid subscription) and the discount the new member got. Read only — the money lives in the append-only wallet ledger.
 */
class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $f = [
            'status' => in_array($request->input('status'), ['subscribed', 'waiting'], true) ? $request->input('status') : '',
            'source' => in_array($request->input('source'), ['link', 'card'], true) ? $request->input('source') : '',
            'search' => trim((string) $request->input('search', '')),
        ];

        $query = Referral::query()->with(['referrer:id,name,phone', 'referred:id,name,phone'])
            ->when($f['status'] === 'subscribed', fn ($q) => $q->whereNotNull('subscribed_at'))
            ->when($f['status'] === 'waiting', fn ($q) => $q->whereNull('subscribed_at'))
            ->when($f['source'], fn ($q, $s) => $q->where('source', $s))
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('referrer_number', 'like', "%{$f['search']}%")
                ->orWhereHas('referrer', fn ($u) => $u->where('name', 'like', "%{$f['search']}%")->orWhere('phone', 'like', "%{$f['search']}%"))
                ->orWhereHas('referred', fn ($u) => $u->where('name', 'like', "%{$f['search']}%")->orWhere('phone', 'like', "%{$f['search']}%"))));

        $totals = Referral::query()->selectRaw('COUNT(*) as n, SUM(subscribed_at IS NOT NULL) as subscribed, SUM(trial_started_at IS NOT NULL) as trials, COALESCE(SUM(CASE WHEN subscribed_at IS NOT NULL THEN discount_amount ELSE 0 END),0) as discounts')->first();
        $paidOut = ReferralReward::query()->where('status', ReferralReward::STATUS_PAID)->selectRaw("COALESCE(SUM(CASE WHEN kind='trial' THEN amount END),0) as trial, COALESCE(SUM(CASE WHEN kind='commission' THEN amount END),0) as commission")->first();
        $reversed = (float) ReferralReward::query()->where('status', ReferralReward::STATUS_REVERSED)->sum('amount');

        $page = $query->latest('id')->paginate(30)->withQueryString();
        $rewards = ReferralReward::query()->whereIn('referral_id', $page->getCollection()->pluck('id'))->get()->groupBy('referral_id');

        return Inertia::render('Admin/Referral/Index', [
            'rows' => $page->getCollection()->map(fn (Referral $r) => [
                'id' => $r->id,
                'referrer' => ['name' => $r->referrer?->name, 'phone' => $r->referrer?->phone, 'number' => $r->referrer_number],
                'referred' => ['name' => $r->referred?->name, 'phone' => $r->referred?->phone],
                'source' => $r->source,
                'joined_at' => $r->created_at?->format('Y-m-d H:i'),
                'subscribed' => $r->isSubscribed(),
                'subscribed_at' => $r->subscribed_at?->format('Y-m-d H:i'),
                'trial_started_at' => $r->trial_started_at?->format('Y-m-d H:i'),
                /* What the referrer got from this member: the trial reward + the commissions (reversed ones do not count). */
                'trial_bonus' => (float) $rewards->get($r->id, collect())->where('kind', 'trial')->where('status', 'paid')->sum('amount'),
                'commission' => (float) $rewards->get($r->id, collect())->where('kind', 'commission')->where('status', 'paid')->sum('amount'),
                'payments' => $rewards->get($r->id, collect())->where('kind', 'commission')->count(),
                'discount' => (float) $r->discount_amount,
                'discount_used' => $r->isSubscribed(),
            ])->all(),
            'meta' => ['last_page' => $page->lastPage(), 'links' => $page->linkCollection()->all()],
            'totals' => [
                'count' => (int) $totals->n, 'trials' => (int) $totals->trials, 'subscribed' => (int) $totals->subscribed,
                'trial_paid' => round((float) $paidOut->trial, 2), 'commission_paid' => round((float) $paidOut->commission, 2),
                'reversed' => round($reversed, 2),
                'paid_to_referrers' => round((float) $paidOut->trial + (float) $paidOut->commission, 2),
                'discounts_given' => round((float) $totals->discounts, 2),
            ],
            'filters' => $f,
        ]);
    }
}
