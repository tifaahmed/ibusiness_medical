<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Visitor\RecordVisitAction;
use App\Actions\Visitor\SummariseVisits;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreVisitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * The website dashboard's chart numbers (key-gated partner route). The
     * website no longer counts visits itself, so it draws these instead.
     */
    public function summary(Request $request, SummariseVisits $summary): JsonResponse
    {
        try {
            return response()->json(['data' => $summary->handle(
                (int) $request->integer('days', SummariseVisits::DEFAULT_DAYS),
                $request->boolean('unique'),
                $request->input('source'),
                $request->input('locale'),
            )]);
        } catch (Throwable $e) {
            Log::error('Visit summary failed', ['route' => $request->route()?->getName(), 'query' => $request->query(), 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Could not build the visit summary.'], 500);
        }
    }
}
