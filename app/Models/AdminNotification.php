<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/** One line in the admin bell. Append-only; who has read it lives in `admin_notification_reads`. */
class AdminNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'title', 'body', 'url', 'data'];

    protected function casts(): array
    {
        return ['title' => 'array', 'body' => 'array', 'data' => 'array'];
    }

    /** The buyer hid an untouched order: the admin is told, and the order stays visible to them. */
    public static function forOrderHidden(Order $order): void
    {
        try {
            static::query()->create([
                'type' => 'order_hidden',
                'title' => ['ar' => "العميل أخفى الطلب {$order->order_code}", 'en' => "Customer hid order {$order->order_code}"],
                'body' => ['ar' => trim((string) $order->customer_full_name).' · لم يعد يريده', 'en' => trim((string) $order->customer_full_name).' · no longer wants it'],
                'url' => route('admin.order.show', $order->order_code, false),
                'data' => ['order_code' => $order->order_code, 'order_id' => $order->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Admin notification for a hidden order could not be written.', ['order_id' => $order->id, 'message' => $e->getMessage()]);
        }
    }

    /** A new order, wherever it came from. Never throws: a failed bell must not cost the buyer their order. */
    public static function forOrder(Order $order): void
    {
        try {
            $source = match ($order->source) {
                \App\Support\PhysicalCard::SOURCE => ['ar' => 'كارت فيزيكال', 'en' => 'Physical card'],
                'relative_fee' => ['ar' => 'رسوم أقارب', 'en' => 'Relative fee'],
                Order::SOURCE_MOBILE_APP => ['ar' => 'من التطبيق', 'en' => 'From the app'],
                default => ['ar' => 'من الموقع', 'en' => 'From the website'],
            };
            $total = number_format((float) $order->total_amount, 2);
            $who = trim((string) $order->customer_full_name);

            static::query()->create([
                'type' => 'order',
                'title' => ['ar' => "طلب جديد {$order->order_code}", 'en' => "New order {$order->order_code}"],
                'body' => [
                    'ar' => trim("{$who} · {$total} ج.م · {$source['ar']}", ' ·'),
                    'en' => trim("{$who} · {$total} EGP · {$source['en']}", ' ·'),
                ],
                'url' => route('admin.order.show', $order->order_code, false),
                'data' => ['order_code' => $order->order_code, 'order_id' => $order->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Admin notification for a new order could not be written.', ['order_id' => $order->id, 'message' => $e->getMessage()]);
        }
    }
}
