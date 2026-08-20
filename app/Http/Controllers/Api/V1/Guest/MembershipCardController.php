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

        $service = new CardGenerationService;
        $url = $service->generate($membershipModel, $mode);

        if (! $url) {
            return response()->json(['error' => 'Failed to generate card'], 500);
        }

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

    /**
     * Serve the back side of the membership card image.
     */
    public function showBack(Request $request, string $membership)
    {
        $membershipModel = Membership::visible()
            ->with(['user', 'partner', 'company', 'cardLayouts.cardTemplate'])
            ->where(function ($query) use ($membership) {
                $query->where('slug', $membership)
                    ->orWhere('membership_number', $membership);
            })
            ->firstOrFail();

        $mode = in_array($request->input('mode'), ['full', 'minimal']) ? $request->input('mode') : 'full';

        $service = new CardGenerationService;
        $url = $service->generateBack($membershipModel, $mode);

        if (! $url) {
            return response()->json(['error' => 'Failed to generate card back'], 500);
        }

        $layout = $membershipModel->cardLayouts()->where('mode', $mode)->first();

        if (! $layout || ! $layout->generated_back_image_path) {
            return response()->json(['error' => 'Failed to generate card back'], 500);
        }

        $path = Storage::disk('public')->path($layout->generated_back_image_path);

        if (! file_exists($path)) {
            return response()->json(['error' => 'Card back not found'], 404);
        }

        $prefix = $membershipModel->membership_number ?? 'membership-card';
        $filename = $prefix.'-'.$mode.'-back.png';

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
