<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SyncMembersUsers extends Command
{
    protected $signature = 'members:sync-users';
    protected $description = 'Ensure every membership has a linked user with password set to membership_number';

    public function handle(): int
    {
        $total = Membership::withTrashed()->count();
        $withUser = Membership::withTrashed()->whereNotNull('user_id')->count();
        $orphans = $total - $withUser;

        $this->table(
            ['Total Memberships', 'With User', 'Without User'],
            [[$total, $withUser, $orphans]]
        );
        $this->newLine();

        $created = $this->createMissingUsers();
        $updated = $this->syncPasswords();

        $this->newLine();
        $this->table(
            ['Action', 'Count'],
            [
                ['Users created', $created],
                ['Passwords synced', $updated],
            ]
        );

        return self::SUCCESS;
    }

    private function createMissingUsers(): int
    {
        $orphans = Membership::withTrashed()
            ->whereNull('user_id')
            ->get(['id', 'membership_number']);

        if ($orphans->isEmpty()) {
            $this->info('No orphan memberships to process.');
            return 0;
        }

        $this->info("Creating users for {$orphans->count()} orphan memberships...");
        $bar = $this->output->createProgressBar($orphans->count());
        $bar->start();

        $pairs = [];

        foreach ($orphans as $membership) {
            $user = User::create([
                'name' => "Member {$membership->membership_number}",
                'email' => null,
                'password' => Hash::make($membership->membership_number),
                'email_verified_at' => now(),
            ]);
            $pairs[] = [
                'membership_id' => $membership->id,
                'user_id' => $user->id,
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Linking users to memberships...');
        DB::transaction(function () use ($pairs) {
            foreach (array_chunk($pairs, 500) as $chunk) {
                foreach ($chunk as $pair) {
                    DB::table('memberships')
                        ->where('id', $pair['membership_id'])
                        ->update(['user_id' => $pair['user_id']]);
                }
            }
        });

        return count($pairs);
    }

    private function syncPasswords(): int
    {
        $this->info('Syncing passwords...');
        $bar = $this->output->createProgressBar();

        $query = Membership::withTrashed()
            ->whereNotNull('user_id')
            ->whereHas('user')
            ->select('membership_number', 'user_id');

        $bar->start($query->count());

        $count = 0;

        DB::transaction(function () use ($query, $bar, &$count) {
            $query->chunk(500, function ($memberships) use ($bar, &$count) {
                foreach ($memberships as $m) {
                    DB::table('users')
                        ->where('id', $m->user_id)
                        ->update([
                            'password' => Hash::make($m->membership_number),
                            'email_verified_at' => DB::raw('COALESCE(email_verified_at, NOW())'),
                        ]);
                    $count++;
                    $bar->advance();
                }
            });
        });

        $bar->finish();

        return $count;
    }
}
