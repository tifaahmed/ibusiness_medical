<?php

namespace App\Http\Controllers\Admin\Order\Ship;

use App\Http\Controllers\Controller as BaseController;
use App\Services\Abs\AbsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminOrderShipCitiesController extends BaseController
{
    public function __construct(
        private readonly AbsClient $client,
    ) {}

    /**
     * The ABS cities of one governorate.
     *
     * The preview loads the cities of whichever governorate it matched, but an
     * admin who corrects that choice needs the other governorate's cities —
     * and ABS scopes cities by governorate, so the list cannot simply be held
     * client-side. This is that second lookup, and it is all it is: a read of
     * a public dropdown, gated by the same permission as the ship button.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'governorate_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            return response()->json([
                'options' => $this->client->cities((int) $validated['governorate_id']),
            ]);
        } catch (\Throwable $exception) {
            Log::error('ABS cities could not be read.', [
                'route' => $request->path(),
                'governorate_id' => $validated['governorate_id'],
                'admin_id' => Auth::id(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }
}
