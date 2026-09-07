<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The courier's own reference for this order, once it has been handed over.
     *
     * `abs_awb` is the air waybill ABS generates when the shipment is booked —
     * the number the parcel is tracked and disputed by from that point on, and
     * the only proof on our side that the order actually reached the courier.
     *
     * It is UNIQUE because it is the guard against double-shipping, not merely
     * a record of it. Booking twice means two couriers sent for one parcel and
     * two delivery fees, and the admin's button is a click away from being
     * pressed twice; a unique index makes the second write fail at the database
     * rather than depending on the check above it having run.
     *
     * Nullable, with no default: most orders have not shipped, and null is
     * exactly "not handed over yet" — which is the question the show page asks
     * to decide whether to offer the button or the tracking number.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('abs_awb')->nullable()->unique()->after('source');
            $table->timestamp('abs_shipped_at')->nullable()->after('abs_awb');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /* The index goes first: dropping a column still carrying a unique
               index fails on MySQL. */
            $table->dropUnique(['abs_awb']);
            $table->dropColumn(['abs_awb', 'abs_shipped_at']);
        });
    }
};
