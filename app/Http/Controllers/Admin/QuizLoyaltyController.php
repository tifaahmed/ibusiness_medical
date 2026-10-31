<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\QuizBadgeDefinition;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\LoyaltyPoints;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Loyalty points for the admin (/admin/loyalty): the quiz's badges — what earns each one, how it looks and how many
 * points it pays — who holds each badge, and extra points given (or taken back) by hand to any member.
 *
 * Nothing here deletes: a badge is switched off, and a grant is corrected by another grant with the opposite sign, so
 * the member's history keeps every line (the ledger is append-only).
 */
class QuizLoyaltyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $definitions = QuizBadgeDefinition::query()->withCount('earned')->orderBy('quiz_key')->orderBy('kind')->orderBy('threshold')->get();

        /* A search finds anyone; with no search the page lists members who hold points or wallet money, most recently active first. */
        $members = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('memberships', fn ($m) => $m->where('membership_number', 'like', "%{$search}%"))))
            ->when($search === '', fn ($query) => $query->where(fn ($q) => $q->whereIn('id', LoyaltyTransaction::query()->select('user_id'))
                ->orWhereIn('id', WalletTransaction::query()->select('user_id'))))
            ->with(['memberships' => fn ($m) => $m->latest('id')->select(['id', 'user_id', 'membership_number'])])
            ->orderByDesc('id')
            ->limit($search === '' ? 30 : 10)->get(['id', 'name', 'phone']);

        $grants = LoyaltyTransaction::query()->where('source', LoyaltyTransaction::SOURCE_ADMIN)->with(['user:id,name,phone', 'creator:id,name'])->latest('id')->limit(30)->get();

        $walletAdjustments = WalletTransaction::query()->where('source', WalletTransaction::SOURCE_ADMIN)->with(['user:id,name,phone', 'creator:id,name'])->latest('id')->limit(30)->get();

        return Inertia::render('Admin/Loyalty/Index', [
            'badges' => $definitions->map(fn (QuizBadgeDefinition $d) => [
                'id' => $d->id,
                'quiz_key' => $d->quiz_key,
                'kind' => $d->kind,
                'threshold' => $d->threshold,
                'title' => $d->title,
                'icon' => $d->icon,
                'reward_points' => $d->reward_points,
                'is_active' => $d->is_active,
                'earned_count' => $d->earned_count,
            ])->all(),
            'sections' => array_values((array) config('loyalty.quiz.sections')),
            /* "1 pound = N points" (edited at /admin/setting) and the speed scale of a right answer, shown at the top of the page. */
            'pointsPerEgp' => LoyaltyPoints::pointsPerEgp(),
            'speedTiers' => collect((array) config('loyalty.quiz.speed_tiers'))->sortKeys()->map(fn ($points, $upTo) => ['up_to_seconds' => (int) $upTo, 'points' => (int) $points])->values()->all(),
            'pointsWrong' => (int) config('loyalty.quiz.points_wrong'),
            'kinds' => QuizBadgeDefinition::KINDS,
            'search' => $search,
            'members' => $members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'membership_number' => $u->memberships->first()?->membership_number,
                'balance' => LoyaltyTransaction::balanceOf($u->id),
                'wallet_balance' => WalletTransaction::balanceOf($u->id),
            ])->all(),
            'wallet_adjustments' => $walletAdjustments->map(fn (WalletTransaction $w) => [
                'id' => $w->id,
                'member_name' => $w->user?->name,
                'member_phone' => $w->user?->phone,
                'amount' => (float) $w->amount,
                'note' => $w->note,
                'by' => $w->creator?->name,
                'at' => $w->created_at?->format('Y-m-d H:i'),
            ])->all(),
            'grants' => $grants->map(fn (LoyaltyTransaction $g) => [
                'id' => $g->id,
                'member_name' => $g->user?->name,
                'member_phone' => $g->user?->phone,
                'points' => $g->points,
                'note' => $g->note,
                'by' => $g->creator?->name,
                'at' => $g->created_at?->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }

    public function storeBadge(Request $request): RedirectResponse
    {
        QuizBadgeDefinition::query()->create($this->validated($request));

        return back()->with('success', 'Badge created.');
    }

    public function updateBadge(Request $request, QuizBadgeDefinition $definition): RedirectResponse
    {
        $definition->update($this->validated($request, $definition));

        return back()->with('success', 'Badge updated.');
    }

    /** The members who hold a badge, newest first. */
    public function holders(QuizBadgeDefinition $definition): JsonResponse
    {
        $rows = $definition->earned()->with('user:id,name,phone')->latest('earned_at')->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($b) => [
            'user_id' => $b->user_id,
            'name' => $b->user?->name,
            'phone' => $b->user?->phone,
            'reward_points' => $b->reward_points,
            'earned_at' => $b->earned_at?->format('Y-m-d H:i'),
        ])->all()]);
    }

    /** Gives (or, with a negative number, takes back) loyalty points. The member sees it in their history with the date and the note. */
    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'points' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            /* Why, in the admin's words: the ledger line then says who did what and why. */
            'note' => ['required', 'string', 'max:255'],
        ]);

        try {
            LoyaltyTransaction::query()->create([
                'user_id' => $data['user_id'],
                'source' => LoyaltyTransaction::SOURCE_ADMIN,
                'client_id' => 'admin:'.Str::uuid(),
                'points' => $data['points'],
                'note' => $data['note'],
                'created_by' => $request->user()->id,
                'answered_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Loyalty grant failed', ['admin_id' => $request->user()->id, 'user_id' => $data['user_id'], 'points' => $data['points'], 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return back()->with('error', 'The points could not be saved.');
        }

        Log::info('Loyalty points granted', ['admin_id' => $request->user()->id, 'user_id' => $data['user_id'], 'points' => $data['points']]);

        return back()->with('success', 'Loyalty points saved.');
    }

    /**
     * Adds (or, with a negative number, takes out) money in a member's wallet. Append-only like every ledger: one new row
     * with the amount, the reason and the admin's id; a mistake is fixed by another row. The wallet never goes below zero.
     */
    public function adjustWallet(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-100000,100000', 'decimal:0,2'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = DB::transaction(function () use ($data, $request) {
                /* Lock the member so two admins (or a purchase) cannot both spend the same balance. */
                User::query()->whereKey($data['user_id'])->lockForUpdate()->firstOrFail();

                $before = WalletTransaction::balanceOf((int) $data['user_id']);
                $amount = round((float) $data['amount'], 2);

                if ($before + $amount < 0) {
                    return ['error' => 'The wallet only holds '.$before.' EGP, so that much cannot be taken out.'];
                }

                WalletTransaction::query()->create([
                    'user_id' => $data['user_id'],
                    'source' => WalletTransaction::SOURCE_ADMIN,
                    'client_id' => 'admin:'.Str::uuid(),
                    'amount' => $amount,
                    'note' => $data['note'],
                    'created_by' => $request->user()->id,
                ]);

                return ['before' => $before, 'after' => round($before + $amount, 2), 'amount' => $amount];
            });
        } catch (Throwable $e) {
            Log::error('Wallet adjustment failed', ['admin_id' => $request->user()->id, 'user_id' => $data['user_id'], 'amount' => $data['amount'], 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return back()->with('error', 'The wallet could not be changed.');
        }

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        Log::info('Wallet adjusted by an admin', ['admin_id' => $request->user()->id, 'user_id' => $data['user_id'], 'amount' => $result['amount'], 'before' => $result['before'], 'after' => $result['after'], 'note' => $data['note']]);

        return back()->with('success', 'Wallet saved.');
    }

    /** One member's whole story, both ledgers newest first: what moved, why, and which admin did it when it was by hand. */
    public function ledger(User $user): JsonResponse
    {
        $points = LoyaltyTransaction::query()->where('user_id', $user->id)->with('creator:id,name')->latest('id')->limit(200)->get()->map(fn (LoyaltyTransaction $l) => [
            'ledger' => 'points',
            'id' => $l->id,
            'source' => $l->source,
            'value' => (int) $l->points,
            'note' => $l->note,
            'by' => $l->creator?->name,
            'at' => $l->created_at?->format('Y-m-d H:i'),
            'ts' => $l->created_at?->timestamp ?? 0,
        ]);

        $wallet = WalletTransaction::query()->where('user_id', $user->id)->with('creator:id,name')->latest('id')->limit(200)->get()->map(fn (WalletTransaction $w) => [
            'ledger' => 'wallet',
            'id' => $w->id,
            'source' => $w->source,
            'value' => (float) $w->amount,
            'note' => $w->note,
            'by' => $w->creator?->name,
            'at' => $w->created_at?->format('Y-m-d H:i'),
            'ts' => $w->created_at?->timestamp ?? 0,
        ]);

        return response()->json([
            'balance' => LoyaltyTransaction::balanceOf($user->id),
            'wallet_balance' => WalletTransaction::balanceOf($user->id),
            'data' => $points->concat($wallet)->sortByDesc(fn (array $row) => [$row['ts'], $row['id']])->values()->take(200)->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?QuizBadgeDefinition $current = null): array
    {
        return $request->validate([
            'quiz_key' => ['required', 'string', Rule::in((array) config('loyalty.quiz.sections'))],
            'kind' => ['required', Rule::in(QuizBadgeDefinition::KINDS)],
            'threshold' => [
                'required', 'integer', 'between:1,1000000',
                Rule::unique('quiz_badge_definitions')->where(fn ($q) => $q->where('quiz_key', $request->input('quiz_key'))->where('kind', $request->input('kind')))->ignore($current?->id),
            ],
            'title' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:16'],
            'reward_points' => ['required', 'integer', 'between:0,1000000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
