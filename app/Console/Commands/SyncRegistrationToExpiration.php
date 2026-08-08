<?php

namespace App\Console\Commands;

use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncRegistrationToExpiration extends Command
{
    protected $signature = 'memberships:sync-registration-to-expiration';

    protected $description = 'Set registration_date to be one year before expiration_date for all memberships';

    public function handle(): int
    {
        $this->info('Syncing registration_date to expiration_date - 1 year...');

        $query = Membership::whereNotNull('expiration_date')
            ->where(function ($q) {
                $q->whereNull('registration_date')
                  ->orWhereColumn('registration_date', '!=', \DB::raw("DATE_SUB(expiration_date, INTERVAL 1 YEAR)"));
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('All memberships already have correct registration_date.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->each(function (Membership $membership) use ($bar) {
            $membership->registration_date = Carbon::parse($membership->expiration_date)->subYear();
            $membership->saveQuietly();
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$count} membership(s) successfully.");

        return Command::SUCCESS;
    }
}
