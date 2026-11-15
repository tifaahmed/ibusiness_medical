<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The app hands us its FCM token so an admin's notification can reach the phone whether the app is open or not.
 * Public like the visit tracker: a guest's phone is registered too; the member is attached only from a verified bearer token,
 * and a later anonymous refresh never detaches a known member.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['nullable', 'in:android,ios'],
            'locale' => ['nullable', 'string', 'max:8'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $row = DevicePushToken::query()->firstOrNew(['token_hash' => hash('sha256', $data['token'])]);
            $row->fill([
                'token' => $data['token'],
                'platform' => $data['platform'] ?? $row->platform,
                'locale' => $data['locale'] ?? $row->locale,
                'app_version' => $data['app_version'] ?? $row->app_version,
                'last_seen_at' => now(),
            ]);

            if ($request->user('sanctum') !== null) {
                $row->user_id = $request->user('sanctum')->id;
            }

            $row->save();
        } catch (Throwable $exception) {
            Log::error('Push token could not be saved.', ['route' => $request->path(), 'message' => $exception->getMessage()]);

            return response()->json(['success' => false], 500);
        }

        return response()->json(['success' => true]);
    }

    /** The app was told to stop (or the member turned notifications off): forget this phone. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        DevicePushToken::query()->where('token_hash', hash('sha256', $data['token']))->delete();

        return response()->json(['success' => true]);
    }
}
