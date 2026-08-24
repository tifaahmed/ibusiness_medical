<?php

use App\Enums\Order\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the order itself came off — pending, success or failed.
     *
     * A third status beside `payment_status` and `delivery_status` rather than
     * something derived from them: those two answer "where is the money" and
     * "where is the parcel", and neither answers "did this order work out".
     * A paid order can still fail (nothing in stock), and an unpaid one can be
     * written off as failed long before its delivery row ever moves.
     *
     * Defaults to pending and is indexed: it is a filter on the list page and
     * the column the bulk action on that page writes.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('order_status', OrderStatusEnum::values())
                ->default(OrderStatusEnum::PENDING->value)
                ->after('delivery_status');

            $table->index('order_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_status']);
            $table->dropColumn('order_status');
        });
    }
};
