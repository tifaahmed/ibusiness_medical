<?php

namespace App\Http\Controllers\Admin\MembershipCard\Show;

use App\Http\Controllers\Concerns\ScopesByMembershipCardCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\MembershipCard\Show\AdminMembershipCardShowResource;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipCardShowController extends BaseController
{
    use ScopesByMembershipCardCreator;

    public function __invoke(Request $request, MembershipCard $card): Response
    {
        $this->assertCanManageCard($card);
        $ids = $card->membership_ids ?: [];

        $memberships = Membership::withTrashed()
            ->whereIn('id', $ids)
            ->with([
                'user:id,name,email,phone,slug',
                'partner:id,title',
                'governorate:id,name',
                'city:id,name',
                'company:id,name',
                'familyMembers' => fn ($q) => $q->orderBy('id'),
            ])
            ->orderByRaw('CAST(membership_number AS UNSIGNED) ASC')
            ->get();

        $card->setRelation('loadedMemberships', $memberships);
        $card->load(['creator:id,name,email', 'media']);
        $card->load('cardTemplate');

        // All memberships in a batch share the same partner_id by construction,
        // so the first one is enough to drive the card preview.
        $partnerId = $memberships->first()?->partner_id;
        $partner = $partnerId ? Partner::find($partnerId) : null;
        $partnerPayload = $partner ? [
            'id' => $partner->id,
            'title' => $partner->title,
            'image' => $partner->image ?: null,
            'card_x' => $partner->card_x,
            'card_y' => $partner->card_y,
            'card_scale' => $partner->card_scale,
        ] : null;

        return Inertia::render('Admin/MembershipCard/Show', [
            'card' => (new AdminMembershipCardShowResource($card))->resolve($request),
            'partner' => $partnerPayload,
            'cardTemplate' => $card->cardTemplate,
        ]);
    }
}
