<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Actions\Locations\NearestBorders;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The closest governorate or city borders to a point — read by the storefront
 * map's "show borders" toggles. Public and key-less like the rest of the
 * guest API.
 */
class NearestBordersController extends Controller
{
    public function __construct(private NearestBorders $borders) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', Rule::in(NearestBorders::LEVELS)],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'governorate_id' => ['nullable', 'integer'],
            'geometry' => ['nullable', 'boolean'],
        ]);

        try {
            $borders = $this->borders->handle(
                $validated['level'],
                (float) $validated['lat'],
                (float) $validated['lng'],
                (int) ($validated['limit'] ?? 4),
                isset($validated['governorate_id']) ? (int) $validated['governorate_id'] : null,
                $request->boolean('geometry', true),
            );
        } catch (Throwable $exception) {
            Log::error('Nearest borders failed.', [
                'route' => 'locations.nearest-borders',
                'level' => $validated['level'],
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json(['borders' => []], 500);
        }

        return response()->json(['borders' => $borders])->header('Cache-Control', 'public, max-age=300');
    }
}
