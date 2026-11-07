<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Paymob\PaymobPayouts;
use App\Support\WalletWithdrawals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Cash-outs to Vodafone Cash: approve / reject / ask Paymob. Money leaves the business here, so it is the TOP admin only. */
class WalletWithdrawalController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);

        $status = in_array($request->input('status'), ['pending', 'processing', 'paid', 'failed', 'rejected', 'canceled'], true) ? $request->input('status') : '';
        $page = WalletWithdrawal::query()->with('user:id,name,phone')->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('Admin/WalletWithdrawal/Index', [
            'rows' => $page->getCollection()->map(fn (WalletWithdrawal $w) => [
                'id' => $w->id, 'member' => ['name' => $w->user?->name, 'phone' => $w->user?->phone], 'amount' => (float) $w->amount,
                'msisdn' => $w->msisdn, 'status' => $w->status, 'created_at' => $w->created_at?->format('Y-m-d H:i'),
                'paid_at' => $w->paid_at?->format('Y-m-d H:i'), 'provider_status' => $w->provider_status, 'provider_code' => $w->provider_code,
                'provider_message' => $w->provider_message, 'refunded' => $w->refunded_at !== null, 'events' => $w->events ?? [],
            ])->all(),
            'meta' => ['last_page' => $page->lastPage(), 'links' => $page->linkCollection()->all()],
            'rules' => WalletWithdrawals::summary() + ['configured' => app(PaymobPayouts::class)->configured(), 'switch' => WalletWithdrawals::enabled()],
            'counts' => WalletWithdrawal::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'filters' => ['status' => $status],
        ]);
    }

    public function approve(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $this->guard($request);

        $sent = WalletWithdrawals::approve($withdrawal, (int) $request->user()->id);

        return back()->with($sent ? 'success' : 'error', $sent ? 'Sent to Paymob. See the status.' : 'Only a waiting request can be approved.');
    }

    public function reject(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $this->guard($request);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:160']])['reason'] ?? null;
        $ok = WalletWithdrawals::reject($withdrawal, (int) $request->user()->id, $reason);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Rejected — the money went back to the member\'s wallet.' : 'Only a waiting request can be rejected.');
    }

    public function refresh(Request $request, WalletWithdrawal $withdrawal): RedirectResponse
    {
        $this->guard($request);

        return back()->with('success', 'Status: '.WalletWithdrawals::refresh($withdrawal, (int) $request->user()->id));
    }
}
