<?php

namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Sales;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ScopesByMembershipCreator;

    /**
     * Display the dashboard.
     */
    public function index(Request $request): Response
    {
        $now = Carbon::now();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // Apply the same union-scope (creator OR partner) the rest of the
        // admin area uses. own/partner admins only see counts and recent
        // members within their slice; full-access admins see everything.
        // Mirrors the listing rule: only completed memberships are counted.
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();
        $listingFilter = function ($mq) use ($scopeFilter, $restricted) {
            $mq->whereNotNull('completed_at');
            if ($restricted) {
                $scopeFilter($mq);
            }
        };

        // Total members (users with at least one membership, excluding trashed)
        $totalMembers = User::whereNull('deleted_at')
            ->whereHas('memberships', $listingFilter)
            ->count();

        // Active members (active memberships that haven't expired, excluding trashed users)
        $activeMembers = Membership::where('is_active', true)
            ->where('expiration_date', '>', $now)
            ->whereNotNull('completed_at')
            ->when($restricted, fn($q) => $q->where($scopeFilter))
            ->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->count();

        // New members this month
        $newThisMonth = Membership::where('registration_date', '>=', $startOfMonth)
            ->whereNotNull('completed_at')
            ->when($restricted, fn($q) => $q->where($scopeFilter))
            ->count();

        // New members last month (for percentage calculation)
        $newLastMonth = Membership::whereBetween('registration_date', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('completed_at')
            ->when($restricted, fn($q) => $q->where($scopeFilter))
            ->count();

        // Calculate percentage change
        $percentageChange = $newLastMonth > 0
            ? round((($newThisMonth - $newLastMonth) / $newLastMonth) * 100, 1)
            : ($newThisMonth > 0 ? 100 : 0);

        // Recent Sales
        $recentSales = Sales::query()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'image' => $s->image,
                'created_at' => $s->created_at?->diffForHumans(),
            ]);

        // Recent members (latest 5 distinct users with their active membership)
        // Exclude trashed users and memberships
        $recentMemberships = Membership::where('is_active', true)
            ->whereNotNull('completed_at')
            ->when($restricted, fn($q) => $q->where($scopeFilter))
            ->whereHas('user', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->with(['user' => function ($q) {
                $q->whereNull('deleted_at');
            }])
            ->latest('registration_date')
            ->limit(5)
            ->get()
            ->filter(function ($membership) {
                return $membership->user !== null;
            })
            ->map(function ($membership) use ($now) {
                $user = $membership->user;
                if (!$user) {
                    return null;
                }
                
                $status = $membership->is_active && $membership->expiration_date > $now ? 'active' : 'inactive';
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'slug' => $user->slug ?? null,
                    'joinedAt' => $membership->registration_date->diffForHumans(),
                    'status' => $status,
                ];
            })
            ->filter(function ($member) {
                return $member !== null;
            })
            ->values();

        // Statistics array matching the frontend structure
        $statistics = [
            [
                'label' => __('admin.dashboard.total_members'),
                'value' => number_format($totalMembers),
                'icon' => 'users',
                'iconColor' => 'text-cyan-400',
                'iconBgColor' => 'from-cyan-500/20 to-cyan-500/10',
                'sublabel' => __('admin.dashboard.active'),
                'sublabelValue' => number_format($activeMembers),
                'sublabelClassName' => 'text-cyan-400',
                'href' => route('admin.dashboard'),
            ],
            // [
            //     'label' => 'New This Month',
            //     'value' => number_format($newThisMonth),
            //     'icon' => 'calendar-check',
            //     'iconColor' => 'text-emerald-500',
            //     'iconBgColor' => 'from-emerald-500/20 to-emerald-500/10',
            //     'sublabel' => 'vs last month',
            //     'sublabelValue' => ($percentageChange >= 0 ? '+' : '') . $percentageChange . '%',
            //     'sublabelClassName' => 'text-emerald-500',
            //     'href' => route('admin.dashboard'),
            // ],
            // [
            //     'label' => 'Pending Approvals',
            //     'value' => '0',
            //     'icon' => 'clock',
            //     'iconColor' => 'text-yellow-500',
            //     'iconBgColor' => 'from-yellow-500/20 to-yellow-500/10',
            //     'sublabel' => 'Require action',
            //     'sublabelValue' => '0',
            //     'sublabelClassName' => 'text-yellow-500',
            //     'href' => route('admin.dashboard'),
            // ],
            // [
            //     'label' => 'Revenue',
            //     'value' => '$0',
            //     'icon' => 'dollar-sign',
            //     'iconColor' => 'text-amber-500',
            //     'iconBgColor' => 'from-amber-500/20 to-amber-500/10',
            //     'sublabel' => 'This month',
            //     'sublabelValue' => '+0%',
            //     'sublabelClassName' => 'text-amber-500',
            //     'href' => route('admin.dashboard'),
            // ],
        ];

        return Inertia::render('Dashboard', [
            'statistics' => $statistics,
            'recentMembers' => $recentMemberships,
            'recentSales' => $recentSales,
        ]);
    }
}

