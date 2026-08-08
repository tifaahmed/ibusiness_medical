<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Membership;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Enums\User\UserRoleEnum;
use Carbon\Carbon;

class MemberUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {   
        try {
            $members = User::factory(10)->create();
            foreach ($members as $member) {
                $member->assignRole(UserRoleEnum::MEMBER);
                
                // Generate a random membership number
                $randomNumber = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $year = Carbon::now()->format('Y');
                $sequence = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
                $membershipNumber = "MEM-{$randomNumber}-{$year}-{$sequence}";
                
                // Randomly decide if membership is active or not (50% chance)
                $isActive = (bool) rand(0, 1);
                
                // Create one membership per user - randomly active or inactive
                if ($isActive) {
                    // Create active membership
                    $membership = Membership::factory()
                        ->for($member)
                        ->active()
                        ->withMembershipNumber($membershipNumber)
                        ->create();
                } else {
                    // Randomly choose between expired or inactive (not expired)
                    if (rand(0, 1)) {
                        // Expired membership
                        $membership = Membership::factory()
                            ->for($member)
                            ->expired()
                            ->withMembershipNumber($membershipNumber)
                            ->create();
                    } else {
                        // Not expired but inactive membership
                        $membership = Membership::factory()
                            ->for($member)
                            ->inactive()
                            ->withMembershipNumber($membershipNumber)
                            ->create();
                    }
                }
                
                $status = $membership->is_active ? 'ACTIVE' : ($membership->expiration_date < Carbon::now() ? 'EXPIRED' : 'INACTIVE');
                
                Log::info('Member created successfully with membership', [
                    'email' => $member->email,
                    'name' => $member->name,
                    'password' => 'password',
                    'membership_number' => $membership->membership_number,
                    'membership_status' => $status,
                ]);
                
                $this->command->info("Member created successfully: " . $member->email);
                $this->command->info("Member name: " . $member->name);
                $this->command->info("Member password: " . 'password');
                $this->command->info("Membership: {$membership->membership_number} ({$status})");
            }
        } catch (\Exception $e) {
            Log::error('Error creating members: ' . $e->getMessage());
            $this->command->error('Error creating members: ' . $e->getMessage());
        }
    }
}
