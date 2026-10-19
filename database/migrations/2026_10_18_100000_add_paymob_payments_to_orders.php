<?php

use App\Models\Setting;
use App\Support\ShopDelivery;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online payment through Paymob, and the extra fee cash on delivery can carry.
 *
 * `cod_fee` archives the fee an order was placed under, the way the delivery
 * columns archive the delivery arrangement: changing the setting later must
 * not rewrite what an old order cost.
 *
 * `order_payment_logs` is the wire-level record of every call to and from the
 * gateway — one row per HTTP exchange, bodies kept verbatim — which is what
 * the order's "Payment" tab reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_email')->nullable()->after('customer_phone');
            $table->decimal('cod_fee', 10, 2)->default(0)->after('delivery_profit');
            $table->unsignedInteger('paymob_attempts')->default(0);
            /* The reference sent as `special_reference`: the order code on the first attempt, `CODE-2`… after. */
            $table->string('paymob_reference')->nullable();
            $table->string('paymob_order_id')->nullable()->index();
            $table->string('paymob_transaction_id')->nullable()->index();
            /* The gateway's own code for the last outcome (txn_response_code, or the HTTP/err code). */
            $table->string('paymob_response_code', 64)->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('receipt_emailed_at')->nullable();
        });

        Schema::create('order_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            /* Groups the exchanges of one flow (create → callback → inquiry…). */
            $table->uuid('correlation_id')->index();
            $table->string('event', 48)->index();
            /* out = we called the gateway, in = the gateway (or the buyer's browser) called us. */
            $table->string('direction', 3);
            $table->string('method', 8)->nullable();
            $table->text('url')->nullable();
            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            /* The gateway's code for this exchange (txn_response_code, error code…). */
            $table->string('gateway_code', 64)->nullable();
            /* Transaction / refund id, so a repeated callback can be recognised. */
            $table->string('gateway_ref')->nullable()->index();
            $table->boolean('success')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('finished_at', 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'id']);
        });

        /* Enum columns only need widening on MySQL; other drivers store them as text. */
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY payment_type ENUM('cod','transfer-wallet','paymob') NOT NULL");
            DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending','accepted','rejected','canceled','refunded') NOT NULL DEFAULT 'pending'");
        }

        foreach (ShopDelivery::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_logs');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_email', 'cod_fee', 'paymob_attempts', 'paymob_reference', 'paymob_order_id',
                'paymob_transaction_id', 'paymob_response_code', 'refunded_amount', 'paid_at',
                'refunded_at', 'receipt_emailed_at',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE orders SET payment_type = 'cod' WHERE payment_type = 'paymob'");
            DB::statement("UPDATE orders SET payment_status = 'canceled' WHERE payment_status = 'refunded'");
            DB::statement("ALTER TABLE orders MODIFY payment_type ENUM('cod','transfer-wallet') NOT NULL");
            DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending','accepted','rejected','canceled') NOT NULL DEFAULT 'pending'");
        }

        Setting::query()->where('slug', ShopDelivery::COD_FEE)->delete();
        SiteSettings::forget();
    }
};
