<?php

namespace App\Http\Controllers\Admin\MembershipCard\Sheet;

use App\Http\Controllers\Concerns\ScopesByMembershipCardCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Membership;
use App\Models\MembershipCard;
use App\Models\Partner;
use App\Support\CardPrint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sheet editor: shows the A4 sheet with the first 10 cards of a batch in their real design and lets the admin move and
 * size every card (drag, or type centimetres). One button saves the whole layout — position and size of EACH card plus the
 * pin strip and margins — as the single `card_sheet_layout` setting, which every PDF (fronts, backs, mirror, batch) then uses.
 */
class AdminMembershipCardSheetController extends BaseController
{
    use ScopesByMembershipCardCreator;

    public function show(Request $request, MembershipCard $card): Response
    {
        $this->assertCanManageCard($card);

        // 10 is the sheet's rule; a couple more are loaded in case the printed size lets more fit.
        $ids = array_slice($card->membership_ids ?: [], 0, 12);
        $memberships = Membership::withTrashed()->whereIn('id', $ids)
            ->orderByRaw('CAST(membership_number AS UNSIGNED) ASC')->get(['id', 'membership_number', 'slug']);

        $card->load('cardTemplate');
        $partner = ($pid = Membership::withTrashed()->whereIn('id', $card->membership_ids ?: [0])->value('partner_id')) ? Partner::find($pid) : null;

        return Inertia::render('Admin/MembershipCard/SheetEditor', [
            'card' => [
                'id' => $card->id,
                'batch_name' => $card->batch_name,
                'display_prefix' => $card->display_prefix,
                'display_groups' => $card->display_groups,
                'layout_overrides' => $card->layout_overrides,
                'partner_logo_url' => $card->getFirstMediaUrl('partner_logo') ?: null,
                'memberships' => $memberships->map(fn ($m) => ['id' => $m->id, 'membership_number' => $m->membership_number, 'slug' => $m->slug])->values(),
            ],
            'partner' => $partner ? ['id' => $partner->id, 'title' => $partner->title, 'image' => $partner->image ?: null, 'card_x' => $partner->card_x, 'card_y' => $partner->card_y, 'card_scale' => $partner->card_scale] : null,
            'cardTemplate' => $card->cardTemplate,
            'cardPrint' => CardPrint::toArray(),
            // Saving is a write: the same permissions as creating a batch (the page itself only needs a view permission).
            'canSave' => $request->user()->canAny(['create membership card patches', 'create own membership card patches', 'create partner membership card patches']),
        ]);
    }

    public function save(Request $request, MembershipCard $card): RedirectResponse
    {
        $this->assertCanManageCard($card);
        $data = $request->validate([
            'top_reserve' => ['nullable', 'numeric'],
            'side_margin' => ['nullable', 'numeric'],
            'pin_y' => ['nullable', 'numeric'],
            'guides' => ['nullable', 'boolean'],
            'pin_dx' => ['nullable', 'numeric'],
            'h_lines' => ['nullable', 'array', 'max:12'],
            'h_lines.*' => ['nullable', 'numeric'],
            'v_lines' => ['nullable', 'array', 'max:4'],
            'v_lines.*' => ['nullable', 'numeric'],
            'print' => ['nullable', 'array'],
            'print.width' => ['nullable', 'numeric'],
            'print.height' => ['nullable', 'numeric'],
            'print.back_width' => ['nullable', 'numeric'],
            'print.back_height' => ['nullable', 'numeric'],
            'cards' => ['nullable', 'array', 'max:40'],
            'cards.*' => ['nullable', 'array'],
            'cards.*.dx' => ['nullable', 'numeric'],
            'cards.*.dy' => ['nullable', 'numeric'],
            'cards.*.w' => ['nullable', 'numeric'],
            'cards.*.h' => ['nullable', 'numeric'],
        ]);

        try {
            // The card sizes first (the sheet's own maths reads them), then the layout — one Save keeps both.
            if (is_array($data['print'] ?? null)) {
                CardPrint::savePrint($data['print']);
            }
            $clean = CardPrint::saveSheet($data);
        } catch (\Throwable $e) {
            Log::error('Failed to save the card sheet layout', ['card_id' => $card->id, 'error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);

            return back()->withErrors(['error' => 'Failed to save the sheet layout. Please try again.']);
        }

        Log::info('Card sheet layout saved', ['card_id' => $card->id, 'by' => $request->user()?->id, 'cards' => count($clean['cards'])]);

        return back()->with('success', 'Sheet layout saved. Every PDF built from now on uses it.');
    }
}
