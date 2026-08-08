<?php

namespace App\Http\Controllers\Admin\MembershipCard\UploadPdf;

use App\Http\Controllers\Concerns\ScopesByMembershipCardCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MembershipCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMembershipCardUploadPdfController extends BaseController
{
    use ScopesByMembershipCardCreator;

    public function __invoke(Request $request, MembershipCard $card): JsonResponse
    {
        $this->assertCanManageCard($card);

        $request->validate([
            'pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
            'partner_logo' => ['nullable', 'file', 'image', 'max:8192'],
        ]);

        $card->clearMediaCollection('pdf');
        $card->addMediaFromRequest('pdf')
            ->usingFileName("batch-{$card->id}.pdf")
            ->toMediaCollection('pdf');

        if ($request->hasFile('partner_logo')) {
            $card->clearMediaCollection('partner_logo');
            $card->addMediaFromRequest('partner_logo')
                ->toMediaCollection('partner_logo');
        }

        return response()->json([
            'pdf_url' => $card->getFirstMediaUrl('pdf'),
            'partner_logo_url' => $card->getFirstMediaUrl('partner_logo') ?: null,
        ]);
    }
}
