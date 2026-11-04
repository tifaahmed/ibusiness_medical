<?php

namespace App\Http\Controllers\Admin\User\Membership\Show;

use App\Http\Controllers\Concerns\ProvidesCardTemplates;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\User\Membership\Show\AdminUserMembershipShowResource;
use App\Models\User;
use App\Support\AdminMemberLoyalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserMembershipShowController extends BaseController
{
    use ProvidesCardTemplates;
    use ScopesByMembershipCreator;

    /**
     * Display the specified user and membership.
     */
    public function __invoke(Request $request, string $user): Response
    {
        $restricted = $this->isMembershipScopeRestricted();
        $scopeFilter = $this->membershipScopeFilter();

        // Fetch user with all memberships and their family members in a single database query
        $withRelations = ['memberships' => function ($query) use ($restricted, $scopeFilter) {
            if ($restricted) {
                $scopeFilter($query);
            }
            $query->with([
                'creator:id,name,email',
                'company',
                'partner',
                'sales',
                'governorate',
                'city',
                'cardLayouts.cardTemplate',
                'memberPayments',
                'subscriptions' => fn ($sq) => $sq->with(['confirmer:id,name', 'media'])->latest('id'),
            ]);
            $query->orderBy('created_at', 'desc');
        }];

        // Relatives are member rows hanging on the primary card
        if (Schema::hasColumn('memberships', 'member_type')) {
            $withRelations['memberships.relatives'] = function ($query) {
                $query->with('user')->orderBy('created_at', 'asc');
            };
        }

        // Only eager load addresses if the table exists
        if (Schema::hasTable('addresses')) {
            $withRelations['memberships.addresses'] = function ($query) {
                $query->with(['governorate', 'city'])->orderBy('created_at', 'asc');
            };
        }

        $user = User::with($withRelations)->where('slug', $user)->firstOrFail();
        $this->assertCanManageUser($user);
        $result = [
            'user' => (new AdminUserMembershipShowResource($user))->toArray($request),
            'cardTemplates' => $this->cardTemplatesByStatus(),
            // Read-only loyalty and wallet picture (points, plays, conversions, transfers, relatives).
            'loyalty' => AdminMemberLoyalty::summary($user),
            // Extra physical / soft cards per member id (primary and relatives).
            'extraCards' => $this->extraCards($user),
            // Other numbers kept as notes (not used to sign in or for SMS).
            'extraPhones' => \App\Models\MemberExtraPhone::query()->where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn ($p) => ['id' => $p->id, 'phone' => $p->phone, 'note' => $p->note, 'wa' => $p->whatsappDigits()])->all(),
            // Staff notes about this member (everyone reads, only the author edits).
            'notes' => $request->user()->can(\App\Enums\User\UserPermissionEnum::VIEW_MEMBER_NOTES)
                ? \App\Models\MemberNote::query()->where('user_id', $user->id)->with('author:id,name')->latest('id')->get()
                    ->map(fn ($n) => \App\Http\Controllers\Admin\MemberNoteController::present($n, $request->user()))->all()
                : null,
            // Card hand-overs to this member (many allowed) + the couriers to choose from.
            'deliveries' => $request->user()->can(\App\Enums\User\UserPermissionEnum::VIEW_CARD_DELIVERIES)
                ? \App\Models\CardDelivery::query()->where('user_id', $user->id)->with(['courier:id,name,phone', 'creator:id,name', 'noteLines.author:id,name', 'media'])->latest('id')->get()
                    ->map(fn ($d) => \App\Http\Controllers\Admin\CardDeliveryController::present($d))->all()
                : null,
            'deliveryCouriers' => \App\Models\DeliveryCourier::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active'])->all(),
            'canManageDeliveries' => $request->user()->can(\App\Enums\User\UserPermissionEnum::MANAGE_CARD_DELIVERIES),
        ];

        return Inertia::render('Admin/User/Membership/Show', $result);
    }

    /** @return array<int, list<array<string, mixed>>> keyed by membership id */
    private function extraCards(User $user): array
    {
        $ids = $user->memberships->flatMap(fn ($m) => collect([$m->id])->merge($m->relatives?->pluck('id') ?? []))->all();

        return \App\Models\MemberExtraCard::query()
            ->whereIn('membership_id', $ids)->orderBy('id')->get()
            ->groupBy('membership_id')
            ->map(fn ($rows) => $rows->map(fn ($c) => [
                'id' => $c->id, 'kind' => $c->kind, 'status' => $c->status, 'card_number' => $c->card_number, 'note' => $c->note,
                'source' => $c->source, 'order_id' => $c->order_id, 'created_at' => $c->created_at?->toDateTimeString(),
            ])->values()->all())
            ->all();
    }
}
