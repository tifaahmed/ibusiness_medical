<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_location_url')->nullable();
            $table->json('name')->nullable();
            $table->string('slug')->unique();
            $table->json('address')->nullable();
            $table->json('area')->nullable();
            // One entry per number — {"number": "...", "type": "landline"} — same
            // shape App\Support\PhoneNumbers already normalises for facility
            // branches.
            $table->json('phone')->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('store_id');
            $table->index('governorate_id');
            $table->index('city_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_branches');
    }
};
