<?php

namespace App\Http\Controllers\Admin\CardGenerator;

use App\Http\Controllers\Concerns\ProvidesCardTemplates;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCardGeneratorController extends BaseController
{
    use ProvidesCardTemplates;

    public function __invoke(Request $request): Response
    {
        $partners = Partner::orderBy('title')
            ->get()
            ->map(fn(Partner $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'image' => $p->image ?: null,
                'card_x' => $p->card_x,
                'card_y' => $p->card_y,
                'card_scale' => $p->card_scale,
            ])
            ->values();

        return Inertia::render('Admin/CardGenerator/Index', [
            'partners' => $partners,
            // Selecting a partner switches the page to the `with_partner`
            // design and clearing it switches to `no_partner`, so the artwork
            // and slots come from /admin/card-templates.
            'templates' => $this->cardTemplatesByStatus(),
            'initial' => [
                'name' => $request->input('name', ''),
                'policy' => $request->input('policy', ''),
                'member' => $request->input('member', ''),
                'member_ar' => $request->input('member_ar', ''),
                'member_en' => $request->input('member_en', ''),
                'status' => $request->input('status', ''),
                'status_ar' => $request->input('status_ar', ''),
                'status_en' => $request->input('status_en', ''),
                'valid' => $request->input('valid', ''),
                'url' => $request->input('url', ''),
                // The member's slug, so the page can tag the QR address with
                // `?slug=` even when the link was made before it carried one.
                'slug' => $request->input('slug', ''),
                'avatar' => $request->input('avatar', ''),
                'partner' => $request->input('partner', ''),
            ],
        ]);
    }
}
