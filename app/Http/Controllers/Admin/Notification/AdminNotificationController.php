<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Http\Controllers\Controller as BaseController;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The bell in the admin header. The page polls `index`; opening a line (or "mark all") posts to `read`.
 * Last 30 days only; unread = not in this admin's `admin_notification_reads`.
 */
class AdminNotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $since = now()->subDays(30);
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        $readIds = DB::table('admin_notification_reads')->where('user_id', $uid)->pluck('admin_notification_id')->flip();
        $items = AdminNotification::query()->where('created_at', '>=', $since)->orderByDesc('id')->limit(30)->get();

        return response()->json([
            'unread' => AdminNotification::query()->where('created_at', '>=', $since)
                ->whereNotIn('id', DB::table('admin_notification_reads')->where('user_id', $uid)->select('admin_notification_id'))->count(),
            'latest_id' => (int) ($items->first()->id ?? 0),
            'items' => $items->map(fn (AdminNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title[$locale] ?? reset($n->title),
                'body' => ($n->body[$locale] ?? null) ?: null,
                'url' => $n->url,
                'at' => $n->created_at?->toAtomString(),
                'read' => $readIds->has($n->id),
            ])->values(),
        ]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:100'], 'ids.*' => ['integer'], 'all' => ['nullable', 'boolean']]);
        $uid = $request->user()->id;

        $ids = $request->boolean('all')
            ? AdminNotification::query()->where('created_at', '>=', now()->subDays(30))->pluck('id')->all()
            : ($data['ids'] ?? []);

        foreach (array_chunk($ids, 200) as $chunk) {
            DB::table('admin_notification_reads')->insertOrIgnore(
                array_map(fn ($id) => ['admin_notification_id' => $id, 'user_id' => $uid, 'read_at' => now()], $chunk)
            );
        }

        return response()->json(['ok' => true]);
    }
}
