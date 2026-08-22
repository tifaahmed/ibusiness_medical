<?php

use App\Enums\Order\DeliveryStatusEnum;
use App\Enums\Order\PaymentStatusEnum;
use App\Enums\Order\PaymentTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique();
            $table->decimal('total_paid', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('total_amount_before_discount', 10, 2)->nullable();
            $table->string('customer_full_name');
            $table->string('customer_phone');
            $table->string('membership_number')->nullable();
            $table->enum('payment_status', PaymentStatusEnum::values())
                ->default(PaymentStatusEnum::PENDING->value);
            $table->enum('delivery_status', DeliveryStatusEnum::values())
                ->default(DeliveryStatusEnum::PENDING->value);
            $table->enum('payment_type', PaymentTypeEnum::values());
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('customer_phone');
            $table->index('membership_number');
            $table->index('payment_status');
            $table->index('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
