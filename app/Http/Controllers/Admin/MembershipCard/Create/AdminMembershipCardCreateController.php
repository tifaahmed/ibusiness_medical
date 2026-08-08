<?php

namespace App\Http\Controllers\Admin\MembershipCard\Create;

use App\Http\Controllers\Concerns\ScopesByMembershipCardCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\Partner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMembershipCardCreateController extends BaseController
{
    use ScopesByMembershipCardCreator;

    public function __invoke(Request $request): Response
    {
        $partnerLocked = $this->cardPartnerLocked();
        $adminPartnerId = $this->currentAdminCardPartnerId();

        if ($partnerLocked && $adminPartnerId === null) {
            throw new AuthorizationException('No partner is attached to your account. Ask a super admin to assign one before creating card batches.');
        }

        $maxNumeric = Membership::withTrashed()
            ->whereRaw("membership_number REGEXP '^[0-9]+$'")
            ->selectRaw('CAST(membership_number AS UNSIGNED) AS n')
            ->orderByDesc('n')
            ->limit(1)
            ->value('n');

        $suggestedStart = $maxNumeric ? ((int) $maxNumeric + 1) : 1000;

        // Mirror the payload shape the existing /admin/card-generator uses —
        // CardPreview.vue replicates that page's minimal-mode renderer and
        // needs the same per-partner layout fields.
        $partners = Partner::query()
            ->when($partnerLocked, fn($q) => $q->where('id', $adminPartnerId))
            ->orderBy('title')
            ->get()
            ->map(fn (Partner $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'image' => $p->image ?: null,
                'card_x' => $p->card_x,
                'card_y' => $p->card_y,
                'card_scale' => $p->card_scale,
            ])
            ->values();

        return Inertia::render('Admin/MembershipCard/Create', [
            'suggested_start' => $suggestedStart,
            'partners' => $partners,
            'partnerLocked' => $partnerLocked,
            'lockedPartnerId' => $partnerLocked ? $adminPartnerId : null,
        ]);
    }
}
