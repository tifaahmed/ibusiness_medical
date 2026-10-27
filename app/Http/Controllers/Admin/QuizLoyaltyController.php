<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\QuizBadgeDefinition;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $members = $search === '' ? collect() : User::query()
            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('memberships', fn ($m) => $m->where('membership_number', 'like', "%{$search}%")))
            ->with(['memberships' => fn ($m) => $m->latest('id')->select(['id', 'user_id', 'membership_number'])])
            ->limit(10)->get(['id', 'name', 'phone']);

        $grants = LoyaltyTransaction::query()->where('source', LoyaltyTransaction::SOURCE_ADMIN)->with(['user:id,name,phone', 'creator:id,name'])->latest('id')->limit(30)->get();

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
            'kinds' => QuizBadgeDefinition::KINDS,
            'search' => $search,
            'members' => $members->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'membership_number' => $u->memberships->first()?->membership_number,
                'balance' => LoyaltyTransaction::balanceOf($u->id),
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
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            LoyaltyTransaction::query()->create([
                'user_id' => $data['user_id'],
                'source' => LoyaltyTransaction::SOURCE_ADMIN,
                'client_id' => 'admin:'.Str::uuid(),
                'points' => $data['points'],
                'note' => $data['note'] ?? null,
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
