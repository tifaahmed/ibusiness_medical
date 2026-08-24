<?php

namespace App\Http\Controllers\Admin\Order\Actions\Update;

use App\Enums\Order\PaymentStatusEnum;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateOrderAction
{
    /**
     * Apply an admin's edit to an order and file what changed.
     *
     * The write and its audit rows go in one transaction on purpose: an order
     * that moved to "accepted" with no log saying who accepted it is worse than
     * an edit that failed outright, because only one of the two can be noticed.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws \Throwable
     */
    public function execute(Order $order, array $validated, Request $request): Order
    {
        return DB::transaction(function () use ($order, $validated, $request) {
            $before = $this->snapshot($order);

            /* A form that posts neither figure leaves the arrangement as it
               was, rather than resetting a charged order to free delivery. */
            $deliveryCost = round((float) ($validated['delivery_cost'] ?? $order->delivery_cost), 2);
            $deliveryPrice = round((float) ($validated['delivery_price'] ?? $order->delivery_price), 2);

            $order->update([
                'customer_full_name' => $validated['customer_full_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_address' => $validated['customer_address'] ?? null,
                'customer_address_type' => $validated['customer_address_type'] ?? null,
                'customer_street' => $validated['customer_street'] ?? null,
                'customer_governorate' => $validated['customer_governorate'] ?? null,
                'customer_city' => $validated['customer_city'] ?? null,
                'customer_building_number' => $validated['customer_building_number'] ?? null,
                'customer_apartment_number' => $validated['customer_apartment_number'] ?? null,
                'customer_floor_number' => $validated['customer_floor_number'] ?? null,
                'customer_special_mark' => $validated['customer_special_mark'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'membership_number' => $validated['membership_number'] ?? null,
                'payment_status' => $validated['payment_status'],
                'delivery_status' => $validated['delivery_status'],
                /* Absent from an older form's post: leave the outcome as it
                   was rather than resetting a settled order to pending. */
                'order_status' => $validated['order_status'] ?? $order->order_status?->value,
                'payment_type' => $validated['payment_type'],
                /* Cleared when the order leaves the canceled state, so a
                   reinstated order does not keep explaining a cancellation
                   that was undone. */
                'cancel_reason' => $validated['payment_status'] === PaymentStatusEnum::CANCELED->value
                    ? ($validated['cancel_reason'] ?? null)
                    : null,
                'total_paid' => $validated['total_paid'],
                'total_amount' => $validated['total_amount'],
                'total_amount_before_discount' => $validated['total_amount_before_discount'] ?? null,
                /*
                 * Delivery, with the profit derived rather than posted — the
                 * same rule the storefront's own endpoint follows, so the three
                 * columns say one thing however an order was edited.
                 */
                'delivery_cost' => $deliveryCost,
                'delivery_price' => $deliveryPrice,
                'delivery_profit' => round($deliveryPrice - $deliveryCost, 2),
                'source' => $validated['source'] ?? null,
                /* `order_code`, `ip_address` and `user_agent` are deliberately
                   not here: the code is the buyer's credential and the other
                   two are the only record of where the order came from. */
            ]);

            $lineChanges = array_key_exists('products', $validated)
                ? $this->syncLines($order, $validated['products'] ?? [])
                : null;

            /*
             * Receipts go last: storing them writes files to disk, which no
             * transaction can roll back — so every validation that could
             * refuse the edit has already passed by the time one does.
             */
            $receiptChanges = array_key_exists('receipts', $validated)
                ? $this->addReceipts($order, $validated['receipts'] ?? [], $request)
                : null;

            $order->refresh()->load(['products', 'media']);
            $after = $this->snapshot($order);

            $this->writeLogs($order, $before, $after, $lineChanges, $request);
            $this->writeReceiptLog($order, $receiptChanges, $request);

            return $order;
        });
    }

    /**
     * Bring the archived lines in line with what the form posted.
     *
     * Lines carry `id` when they already existed; anything without one is new.
     * A line the form did not send back is one the admin removed, and it is
     * deleted rather than zeroed — a quantity-zero line is not a line.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{added: int, updated: int, removed: int, removed_names: array<int, string>}
     */
    private function syncLines(Order $order, array $lines): array
    {
        $existing = $order->products()->get()->keyBy('id');
        $keptIds = [];
        $added = 0;
        $updated = 0;

        foreach ($lines as $line) {
            $attributes = $this->lineAttributes($line);
            $id = isset($line['id']) ? (int) $line['id'] : null;

            /* Only rows that belong to THIS order may be matched by id — a
               posted id from another order would otherwise let one order's
               edit form rewrite another's archive. */
            $row = $id !== null ? $existing->get($id) : null;

            if ($row === null) {
                $order->products()->create($attributes);
                $added++;

                continue;
            }

            $row->fill($attributes);

            if ($row->isDirty()) {
                $row->save();
                $updated++;
            }

            $keptIds[] = $row->id;
        }

        $removed = $existing->reject(fn (OrderProduct $row) => in_array($row->id, $keptIds, true));

        foreach ($removed as $row) {
            $row->delete();
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed->count(),
            'removed_names' => $removed->map(fn (OrderProduct $row) => (string) $row->name)->values()->all(),
        ];
    }

    /**
     * One posted line as columns.
     *
     * `line_total` is derived here and never taken from the form: a total that
     * can disagree with its own quantity and price is a total nobody can
     * reconcile against the order.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function lineAttributes(array $line): array
    {
        $quantity = (int) $line['quantity'];
        $unitPrice = isset($line['new_price']) && $line['new_price'] !== '' ? (float) $line['new_price'] : 0.0;

        return [
            'product_id' => $line['product_id'] ?? null,
            'name' => $line['name'],
            'slug' => $line['slug'] ?? null,
            'image' => $line['image'] ?? null,
            'quantity' => $quantity,
            'old_price' => $this->decimal($line['old_price'] ?? null),
            'new_price' => $this->decimal($line['new_price'] ?? null),
            'line_total' => round($unitPrice * $quantity, 2),
            'cost_price' => $this->decimal($line['cost_price'] ?? null),
            'profit_price' => $this->decimal($line['profit_price'] ?? null),
        ];
    }

    private function decimal(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * Add whatever receipts the form posted. Nothing is ever taken away.
     *
     * The collection is append-only — see `Order::RECEIPT_COLLECTION`. There is
     * no removal path here and no cap to check against: an admin who does not
     * believe a receipt moves `payment_status`, which `order_logs` attributes
     * and dates, rather than deleting the evidence, which it cannot.
     *
     * @param  array<int, UploadedFile>  $newFiles
     * @return array{before: list<string>, after: list<string>, added: int}
     *
     * @throws \Throwable
     */
    private function addReceipts(Order $order, array $newFiles, Request $request): array
    {
        /** @var list<string> $before */
        $before = $order->getMedia(Order::RECEIPT_COLLECTION)
            ->sortBy('id')
            ->map(fn ($media) => $media->file_name)
            ->values()
            ->all();

        foreach ($newFiles as $file) {
            try {
                $order->addMedia($file)
                    ->toMediaCollection(Order::RECEIPT_COLLECTION);
            } catch (\Throwable $exception) {
                Log::error('Order receipt could not be stored during order update.', [
                    'route' => $request->path(),
                    'order_code' => $order->order_code,
                    'file_name' => $file instanceof UploadedFile ? $file->getClientOriginalName() : null,
                    'admin_id' => Auth::id(),
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                throw $exception;
            }
        }

        /* `getMedia` serves the loaded relation once it is loaded, so the new
           rows are invisible to it until the model is re-read — an `after`
           taken without this equals `before`, and the change looks like none. */
        $order->refresh();

        $after = $order->getMedia(Order::RECEIPT_COLLECTION)
            ->sortBy('id')
            ->map(fn ($media) => $media->file_name)
            ->values()
            ->all();

        return [
            'before' => $before,
            'after' => $after,
            'added' => count($newFiles),
        ];
    }

    /**
     * File a receipt change as its own audit row, the way a line rewrite gets
     * one — an admin confirming or clearing transfer evidence is exactly the
     * sort of thing someone goes looking for by name later. Nothing changed,
     * nothing logged.
     *
     * @param  array{before: list<string>, after: list<string>, added: int}|null  $receiptChanges
     */
    private function writeReceiptLog(Order $order, ?array $receiptChanges, Request $request): void
    {
        if ($receiptChanges === null || $receiptChanges['before'] === $receiptChanges['after']) {
            return;
        }

        OrderLog::record(
            $order->id,
            Auth::id(),
            OrderLog::ACTION_UPDATED,
            ['receipts' => $receiptChanges['before']],
            [
                'receipts' => $receiptChanges['after'],
                'added' => $receiptChanges['added'],
            ],
            $request,
        );

        Log::info('Order receipts added by admin.', [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'admin_id' => Auth::id(),
            'receipts_added' => $receiptChanges['added'],
        ]);
    }

    /**
     * The order as the audit trail records it — the editable fields plus a
     * digest of the lines, so a re-priced line shows up as a change rather
     * than hiding behind an unchanged order row.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            'customer_full_name' => $order->customer_full_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $order->customer_address,
            'customer_address_type' => $order->customer_address_type?->value,
            'customer_street' => $order->customer_street,
            'customer_governorate' => $order->customer_governorate,
            'customer_city' => $order->customer_city,
            'customer_building_number' => $order->customer_building_number,
            'customer_apartment_number' => $order->customer_apartment_number,
            'customer_floor_number' => $order->customer_floor_number,
            'customer_special_mark' => $order->customer_special_mark,
            'notes' => $order->notes,
            'membership_number' => $order->membership_number,
            'payment_status' => $order->payment_status?->value,
            'delivery_status' => $order->delivery_status?->value,
            'order_status' => $order->order_status?->value,
            'payment_type' => $order->payment_type?->value,
            'cancel_reason' => $order->cancel_reason,
            'total_paid' => (float) $order->total_paid,
            'total_amount' => (float) $order->total_amount,
            'total_amount_before_discount' => $order->total_amount_before_discount === null
                ? null
                : (float) $order->total_amount_before_discount,
            'delivery_cost' => (float) $order->delivery_cost,
            'delivery_price' => (float) $order->delivery_price,
            'delivery_profit' => (float) $order->delivery_profit,
            'source' => $order->source,
            'products' => $order->products->map(fn (OrderProduct $line) => [
                'id' => $line->id,
                'name' => (string) $line->name,
                'quantity' => (int) $line->quantity,
                'new_price' => $line->new_price === null ? null : (float) $line->new_price,
                'line_total' => $line->line_total === null ? null : (float) $line->line_total,
            ])->all(),
        ];
    }

    /**
     * File the edit: one `updated` row for the whole change, plus a row of its
     * own for each thing an admin would go looking for by name — a payment
     * moved, a delivery moved, an order canceled, the lines rewritten.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array{added: int, updated: int, removed: int, removed_names: array<int, string>}|null  $lineChanges
     */
    private function writeLogs(
        Order $order,
        array $before,
        array $after,
        ?array $lineChanges,
        Request $request,
    ): void {
        $adminId = Auth::id();
        $changed = array_keys(array_filter(
            $after,
            fn ($value, $key) => ! array_key_exists($key, $before) || $before[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changed === []) {
            return;
        }

        /* Only the fields that moved: a log that repeats every column on every
           save is one nobody reads. */
        $old = array_intersect_key($before, array_flip($changed));
        $new = array_intersect_key($after, array_flip($changed));

        OrderLog::record($order->id, $adminId, OrderLog::ACTION_UPDATED, $old, $new, $request);

        if ($before['payment_status'] !== $after['payment_status']) {
            OrderLog::record(
                $order->id,
                $adminId,
                OrderLog::ACTION_PAYMENT_STATUS_CHANGED,
                ['payment_status' => $before['payment_status']],
                ['payment_status' => $after['payment_status']],
                $request,
            );

            if ($after['payment_status'] === PaymentStatusEnum::CANCELED->value) {
                OrderLog::record(
                    $order->id,
                    $adminId,
                    OrderLog::ACTION_CANCELED,
                    ['payment_status' => $before['payment_status']],
                    ['cancel_reason' => $after['cancel_reason']],
                    $request,
                );
            }
        }

        if ($before['delivery_status'] !== $after['delivery_status']) {
            OrderLog::record(
                $order->id,
                $adminId,
                OrderLog::ACTION_DELIVERY_STATUS_CHANGED,
                ['delivery_status' => $before['delivery_status']],
                ['delivery_status' => $after['delivery_status']],
                $request,
            );
        }

        if ($before['order_status'] !== $after['order_status']) {
            OrderLog::record(
                $order->id,
                $adminId,
                OrderLog::ACTION_ORDER_STATUS_CHANGED,
                ['order_status' => $before['order_status']],
                ['order_status' => $after['order_status']],
                $request,
            );
        }

        if ($lineChanges !== null && $before['products'] !== $after['products']) {
            OrderLog::record(
                $order->id,
                $adminId,
                OrderLog::ACTION_PRODUCTS_CHANGED,
                ['products' => $before['products']],
                [
                    'products' => $after['products'],
                    'added' => $lineChanges['added'],
                    'updated' => $lineChanges['updated'],
                    'removed' => $lineChanges['removed'],
                    'removed_names' => $lineChanges['removed_names'],
                ],
                $request,
            );
        }

        Log::info('Order updated by admin.', [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'admin_id' => $adminId,
            'changed_fields' => $changed,
        ]);
    }
}
