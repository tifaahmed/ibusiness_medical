<?php

namespace App\Console\Commands;

use App\Enums\Membership\PaymentTypeEnum;
use App\Models\Membership;
use Illuminate\Console\Command;

class FillPaymentType extends Command
{
    protected $signature = 'memberships:fill-payment-type';

    protected $description = 'Fill payment_type for paid memberships that have none';

    public function handle(): int
    {
        $this->info('Filling payment_type for paid memberships...');

        $count = 0;

        Membership::where('is_paid', true)
            ->whereNull('payment_type')
            ->chunkById(200, function ($memberships) use (&$count) {
                foreach ($memberships as $membership) {
                    $membership->payment_type = $membership->partner_id
                        ? PaymentTypeEnum::MONTHLY->value
                        : PaymentTypeEnum::YEARLY->value;
                    $membership->save();
                    $count++;
                }
            });

        $this->info("Filled payment_type for {$count} membership(s).");

        return Command::SUCCESS;
    }
}
