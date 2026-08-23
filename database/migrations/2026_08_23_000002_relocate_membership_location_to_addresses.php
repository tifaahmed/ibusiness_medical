<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relocate the location data old memberships carry (governorate_id /
     * city_id on `memberships`) into the new per-address `addresses` table
     * as each member's HOME address.
     *
     * Runs in chunks, skips memberships that already have an address row and
     * includes soft-deleted memberships so trashed members keep their history.
     */
    public function up(): void
    {
        if (! Schema::hasTable('addresses') || ! Schema::hasTable('memberships')) {
            return;
        }

        DB::table('memberships')
            ->where(function ($query) {
                $query->whereNotNull('governorate_id')
                    ->orWhereNotNull('city_id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('addresses')
                    ->whereColumn('addresses.membership_id', 'memberships.id');
            })
            ->orderBy('id')
            ->chunkById(500, function ($memberships) {
                $now = now();

                $rows = $memberships->map(fn ($membership) => [
                    'membership_id' => $membership->id,
                    'type' => 'home',
                    'address' => null,
                    'street' => null,
                    'governorate_id' => $membership->governorate_id,
                    'city_id' => $membership->city_id,
                    'building_number' => null,
                    'apartment_number' => null,
                    'floor_number' => null,
                    'special_mark' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                try {
                    DB::table('addresses')->insert($rows);
                } catch (\Throwable $e) {
                    // A relocation failure must never leave the deploy half-done
                    // silently — log the batch and rethrow so the migration fails loudly.
                    \Log::error('Failed to relocate membership location data to addresses', [
                        'migration' => '2026_08_23_000002_relocate_membership_location_to_addresses',
                        'membership_ids' => $memberships->pluck('id')->all(),
                        'error_message' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            });
    }

    /**
     * Undo: remove only the bare "relocation stub" rows this migration created —
     * home addresses with no extra detail that still match their membership's
     * governorate/city. Anything an admin has since filled in is kept.
     */
    public function down(): void
    {
        if (! Schema::hasTable('addresses') || ! Schema::hasTable('memberships')) {
            return;
        }

        DB::table('addresses as a')
            ->join('memberships as m', 'm.id', '=', 'a.membership_id')
            ->where('a.type', 'home')
            ->whereNull('a.address')
            ->whereNull('a.street')
            ->whereNull('a.building_number')
            ->whereNull('a.apartment_number')
            ->whereNull('a.floor_number')
            ->whereNull('a.special_mark')
            ->whereColumn('a.governorate_id', 'm.governorate_id')
            ->whereColumn('a.city_id', 'm.city_id')
            ->delete();
    }
};
