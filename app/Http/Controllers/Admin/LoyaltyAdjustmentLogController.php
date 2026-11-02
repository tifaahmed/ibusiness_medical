<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every loyalty-point and wallet line an ADMIN wrote (created_by is set): given or taken by hand, moved between members,
 * converted. Who did it, to which member and card, how much and why. Read only — the ledgers stay append-only.
 */
class LoyaltyAdjustmentLogController extends Controller
{
    public function index(Request $request): Response
    {
        $f = [
            'ledger' => in_array($request->input('ledger'), ['points', 'wallet'], true) ? $request->input('ledger') : '',
            'direction' => in_array($request->input('direction'), ['given', 'taken'], true) ? $request->input('direction') : '',
            'admin_id' => $request->filled('admin_id') ? (int) $request->input('admin_id') : null,
            'search' => trim((string) $request->input('search', '')),
            'from' => $request->input('from') ?: null,
            'to' => $request->input('to') ?: null,
        ];

        $userIds = $f['search'] === '' ? null : User::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$f['search']}%")->orWhere('phone', 'like', "%{$f['search']}%")
                ->orWhereHas('memberships', fn ($m) => $m->where('membership_number', 'like', "%{$f['search']}%")))
            ->limit(500)->pluck('id')->all();

        $lines = function (string $table, string $valueColumn, string $ledger) use ($f, $userIds): Builder {
            return DB::table($table)
                ->select(['id', 'user_id', DB::raw("'{$ledger}' as ledger"), DB::raw("{$valueColumn} as value"), 'source', 'note', 'created_by', 'created_at'])
                ->whereNotNull('created_by')
                ->when($f['direction'] === 'given', fn ($q) => $q->where($valueColumn, '>', 0))
                ->when($f['direction'] === 'taken', fn ($q) => $q->where($valueColumn, '<', 0))
                ->when($f['admin_id'], fn ($q, $id) => $q->where('created_by', $id))
                ->when($f['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($f['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds ?: [0]));
        };

        $sources = [];
        if ($f['ledger'] !== 'wallet') {
            $sources[] = $lines('loyalty_transactions', 'points', 'points');
        }
        if ($f['ledger'] !== 'points') {
            $sources[] = $lines('wallet_transactions', 'amount', 'wallet');
        }
        $union = array_shift($sources);
        foreach ($sources as $s) {
            $union->unionAll($s);
        }

        $base = DB::query()->fromSub($union, 't');
        $totals = (clone $base)->selectRaw("ledger, SUM(CASE WHEN value > 0 THEN value ELSE 0 END) as given, SUM(CASE WHEN value < 0 THEN value ELSE 0 END) as taken, COUNT(*) as n")->groupBy('ledger')->get()->keyBy('ledger');

        $page = (clone $base)->orderByDesc('created_at')->orderByDesc('id')->paginate(30)->withQueryString();

        $users = User::query()->whereIn('id', collect($page->items())->pluck('user_id')->unique())
            ->with(['memberships' => fn ($m) => $m->latest('id')->select(['id', 'user_id', 'membership_number'])])->get(['id', 'name', 'phone', 'slug'])->keyBy('id');
        $admins = User::query()->whereIn('id', collect($page->items())->pluck('created_by')->unique())->get(['id', 'name'])->keyBy('id');

        return Inertia::render('Admin/Loyalty/Log', [
            'rows' => collect($page->items())->map(function ($r) use ($users, $admins) {
                $u = $users[$r->user_id] ?? null;

                return [
                    'key' => $r->ledger.$r->id,
                    'ledger' => $r->ledger,
                    'value' => $r->ledger === 'wallet' ? (float) $r->value : (int) $r->value,
                    'source' => $r->source,
                    'note' => $r->note,
                    'at' => \Illuminate\Support\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'admin' => $admins[$r->created_by]->name ?? ('#'.$r->created_by),
                    'member' => $u ? [
                        'id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'slug' => $u->slug,
                        'membership_number' => $u->memberships->first()?->membership_number,
                    ] : null,
                ];
            })->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'links' => $page->linkCollection()->toArray()],
            'totals' => [
                'points' => ['given' => (int) ($totals['points']->given ?? 0), 'taken' => (int) ($totals['points']->taken ?? 0), 'n' => (int) ($totals['points']->n ?? 0)],
                'wallet' => ['given' => (float) ($totals['wallet']->given ?? 0), 'taken' => (float) ($totals['wallet']->taken ?? 0), 'n' => (int) ($totals['wallet']->n ?? 0)],
            ],
            'filters' => $f,
            'adminOptions' => User::query()->whereIn('id', DB::table('loyalty_transactions')->whereNotNull('created_by')->select('created_by')
                ->union(DB::table('wallet_transactions')->whereNotNull('created_by')->select('created_by')))->orderBy('name')->get(['id', 'name'])->all(),
        ]);
    }
}
