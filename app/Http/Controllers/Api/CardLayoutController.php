<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardLayout;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CardLayoutController extends Controller
{
    public function __invoke(Request $request, string $membershipNumber): JsonResponse
    {
        $membership = Membership::withTrashed()->where('membership_number', $membershipNumber)->first();

        if (!$membership) {
            return response()->json(['message' => 'Membership not found.'], 404);
        }

        $validated = $request->validate([
            'mode' => ['nullable', 'string', 'in:full,minimal'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'partner_x' => ['nullable', 'numeric'],
            'partner_y' => ['nullable', 'numeric'],
            'partner_scale' => ['nullable', 'numeric'],
            'photo_x' => ['nullable', 'numeric'],
            'photo_y' => ['nullable', 'numeric'],
            'photo_scale' => ['nullable', 'numeric'],
            'name_x' => ['nullable', 'numeric'],
            'name_y' => ['nullable', 'numeric'],
            'name_scale' => ['nullable', 'numeric'],
            'name_color' => ['nullable', 'string', 'max:7'],
            'fields_x' => ['nullable', 'numeric'],
            'fields_y' => ['nullable', 'numeric'],
            'fields_scale' => ['nullable', 'numeric'],
            'fields_color' => ['nullable', 'string', 'max:7'],
            'qr_x' => ['nullable', 'numeric'],
            'qr_y' => ['nullable', 'numeric'],
            'qr_scale' => ['nullable', 'numeric'],
            'image' => ['nullable', 'string'],
        ]);

        $mode = $validated['mode'] ?? 'full';

        $layout = CardLayout::updateOrCreate(
            ['membership_id' => $membership->id, 'mode' => $mode],
            $request->only([
                'partner_id', 'partner_x', 'partner_y', 'partner_scale',
                'photo_x', 'photo_y', 'photo_scale',
                'name_x', 'name_y', 'name_scale', 'name_color',
                'fields_x', 'fields_y', 'fields_scale', 'fields_color',
                'qr_x', 'qr_y', 'qr_scale',
            ])
        );

        if (!empty($validated['image'])) {
            $oldPath = $layout->generated_image_path;
            $imageData = $validated['image'];
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
            $imageData = base64_decode($imageData);

            $filename = 'card-' . $layout->membership_id . '-' . time() . '.png';
            $path = 'cards/' . $filename;

            Storage::disk('public')->put($path, $imageData);

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $layout->update(['generated_image_path' => $path]);
        }

        return response()->json([
            'layout' => $layout,
            'image_url' => $layout->generated_image_path
                ? Storage::disk('public')->url($layout->generated_image_path)
                : null,
        ]);
    }
}