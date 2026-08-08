<?php

namespace App\Console\Commands;

use App\Enums\User\UserRoleEnum;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Console\Command;

class AssignMembershipsCreatorToSuperAdmin extends Command
{
    protected $signature = 'memberships:assign-creator-super-admin
                            {--user-id= : Specific super admin user id to assign as creator (defaults to the oldest super admin)}
                            {--overwrite : Also overwrite memberships that already have a creator}';

    protected $description = 'Backfill memberships.created_by with a super admin user';

    public function handle(): int
    {
        $superAdmin = $this->resolveSuperAdmin();
        if (!$superAdmin) {
            $this->error('No super admin user found. Pass --user-id=<id> or assign the super_admin role first.');
            return self::FAILURE;
        }

        $query = Membership::withTrashed();
        if (!$this->option('overwrite')) {
            $query->whereNull('created_by');
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No memberships need backfilling.');
            return self::SUCCESS;
        }

        $updated = $query->update(['created_by' => $superAdmin->id]);

        $this->info("Set created_by = {$superAdmin->id} ({$superAdmin->name}) on {$updated} membership(s).");
        return self::SUCCESS;
    }

    private function resolveSuperAdmin(): ?User
    {
        if ($id = $this->option('user-id')) {
            return User::find($id);
        }
        return User::role(UserRoleEnum::SUPER_ADMIN)->orderBy('id')->first();
    }
}
