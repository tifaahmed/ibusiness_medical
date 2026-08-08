<?php

namespace App\Console\Commands;

use App\Models\Membership;
use Illuminate\Console\Command;

class DeactivateExpiredMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate memberships that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired memberships...');

        $expiredCount = Membership::where('is_active', true)
            ->where('expiration_date', '<', now())
            ->update(['is_active' => false]);

        if ($expiredCount > 0) {
            $this->info("Deactivated {$expiredCount} expired membership(s).");
        } else {
            $this->info('No expired memberships found.');
        }

        return Command::SUCCESS;
    }
}

