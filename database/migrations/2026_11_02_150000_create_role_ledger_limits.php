<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A monthly cap per ROLE on the four hand-written balance permissions (add / take × loyalty points / wallet money): the default for
 * every admin holding the role. An admin's own row in `admin_ledger_limits` still wins. `monthly_limit` NULL = unlimited. Add-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_ledger_limits')) {
            Schema::create('role_ledger_limits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->string('permission', 64);
                $table->decimal('monthly_limit', 12, 2)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['role_id', 'permission']);
            });
        }
    }

    public function down(): void
    {
        // Left in place: dropping the table would lose the caps.
    }
};
