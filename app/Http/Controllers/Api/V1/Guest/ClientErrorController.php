<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\ClientErrorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClientErrorController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'nullable|string|max:50',
            'app_version' => 'nullable|string|max:50',
            'route' => 'nullable|string|max:255',
            'fatal' => 'nullable|boolean',
            'message' => 'required|string|max:5000',
            'stack' => 'nullable|string|max:20000',
            'extra' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            ClientErrorLog::create([
                ...$validator->validated(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['success' => true], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store client error log: ' . $e->getMessage());

            return response()->json(['success' => false], 500);
        }
    }
}
