<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_id')->constrained('memberships')->onDelete('cascade');
            $table->string('name');
            $table->enum('relationship', ['wife', 'husband', 'son', 'daughter', 'father', 'mother', 'brother', 'sister']);
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('membership_id');
            $table->index('relationship');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
