<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Visitor\RecordVisitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVisitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class VisitController extends Controller
{
    public function store(StoreVisitRequest $request, RecordVisitAction $record): JsonResponse
    {
        try {
            // Verified identity only: a Sanctum token the caller holds (the app's own,
            // or the member token the website forwards). Typed phone/email never link.
            $visit = $record->handle($request->visit(), $request->user('sanctum'));
        } catch (Throwable $e) {
            Log::error('Visit not recorded', [
                'route' => $request->route()?->getName(),
                'page' => $request->input('page'),
                'visitor_key' => $request->input('visitor_key'),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true, 'id' => $visit->id], 201);
    }
}
