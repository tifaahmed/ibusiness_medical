<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::whereIn('name', [
            'manage membership card patches',
            'manage own membership card patches',
            'manage partner membership card patches',
        ])->delete();

        collect([
            'view membership card patches',
            'create membership card patches',
            'view own membership card patches',
            'create own membership card patches',
            'view partner membership card patches',
            'create partner membership card patches',
        ])->each(fn ($name) => Permission::findOrCreate($name, 'web'));
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'view membership card patches',
            'create membership card patches',
            'view own membership card patches',
            'create own membership card patches',
            'view partner membership card patches',
            'create partner membership card patches',
        ])->delete();

        collect([
            'manage membership card patches',
            'manage own membership card patches',
            'manage partner membership card patches',
        ])->each(fn ($name) => Permission::findOrCreate($name, 'web'));
    }
};
