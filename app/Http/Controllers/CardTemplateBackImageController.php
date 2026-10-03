<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Services\CardBackRenderer;

/**
 * The rendered back of a card template, public like the card images
 * themselves. The `v` query string is only a cache-buster.
 */
class CardTemplateBackImageController extends Controller
{
    public function __invoke(CardTemplate $cardTemplate, CardBackRenderer $renderer)
    {
        abort_unless($cardTemplate->hasCustomBack(), 404);

        $path = $renderer->ensure($cardTemplate);
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
