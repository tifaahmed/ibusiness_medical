<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\CardGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MembershipCardController extends Controller
{
    public function show(Request $request, string $membership)
    {
        $membershipModel = Membership::visible()
            ->with(['user', 'partner', 'company', 'cardLayouts.cardTemplate'])
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $mode = in_array($request->input('mode'), ['full', 'minimal']) ? $request->input('mode') : 'full';

        /*
         * Serve the pre-generated image when one exists.
         *
         * The admin generates a card from the card-generator page and that
         * result is stored in `generated_image_path`. Deilar and the mobile
         * app should show that exact image — not a fresh render — so the
         * member always sees what the admin published. A new render only
         * happens when no image has been produced yet (first visit after a
         * membership is created, or after the layout record was reset).
         */
        $layout = $membershipModel->cardLayouts()->where('mode', $mode)->first();

        if ($layout && $layout->generated_image_path) {
            $path = Storage::disk('public')->path($layout->generated_image_path);

            if (file_exists($path)) {
                $prefix = $membershipModel->membership_number ?? 'membership-card';
                $filename = $prefix.'-'.$mode.'.png';

                return response()->file($path, [
                    'Content-Type' => 'image/png',
                    'Content-Disposition' => 'inline; filename="'.$filename.'"',
                ]);
            }
        }

        /*
         * No pre-generated image — render one now from the default template
         * (or whatever template is linked to the layout). The result is stored
         * in `generated_image_path` so the next request is served instantly.
         */
        $service = new CardGenerationService;
        $url = $service->generate($membershipModel, $mode);

        if (! $url) {
            return response()->json(['error' => 'Failed to generate card'], 500);
        }

        // Reload the layout so we get the path that was just written.
        $layout = $membershipModel->cardLayouts()->where('mode', $mode)->first();

        if (! $layout || ! $layout->generated_image_path) {
            return response()->json(['error' => 'Failed to generate card'], 500);
        }

        $path = Storage::disk('public')->path($layout->generated_image_path);

        if (! file_exists($path)) {
            return response()->json(['error' => 'Card not found'], 404);
        }

        $prefix = $membershipModel->membership_number ?? 'membership-card';
        $filename = $prefix.'-'.$mode.'.png';

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function url(Request $request, string $membership)
    {
        $membershipModel = Membership::visible()
            ->with(['user', 'partner', 'company', 'cardLayouts.cardTemplate'])
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $mode = in_array($request->input('mode'), ['full', 'minimal']) ? $request->input('mode') : 'full';

        // Return the existing URL when the card is already generated.
        $layout = $membershipModel->cardLayouts()->where('mode', $mode)->first();

        if ($layout && $layout->generated_image_path) {
            $path = Storage::disk('public')->path($layout->generated_image_path);

            if (file_exists($path)) {
                return response()->json([
                    'card_url' => Storage::disk('public')->url($layout->generated_image_path),
                    'mode' => $mode,
                ]);
            }
        }

        $service = new CardGenerationService;
        $cardUrl = $service->generate($membershipModel, $mode);

        if (! $cardUrl) {
            return response()->json(['error' => 'Failed to generate card'], 500);
        }

        return response()->json([
            'card_url' => $cardUrl,
            'mode' => $mode,
        ]);
    }
}
