<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions;

use App\Models\User;
use App\Models\Membership;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class StoreUserMembershipAction
{
    public function __construct(
        private MediaService $mediaService
    ) {}

    /**
     * Execute the action to store a user with membership.
     *
     * @param array $validated
     * @param UploadedFile|null $avatar
     * @return array{user: User, membership: Membership}
     * @throws \Exception
     */
    public function execute(array $validated, ?UploadedFile $avatar = null): array
    {
        DB::beginTransaction();

        try {
            // Create the user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Deactivate all existing memberships for this user (in case user already exists)
            $user->memberships()->update(['is_active' => false]);

            // Create the membership and set it as active
            $membership = $user->memberships()->create([
                'membership_number' => $validated['membership_number'],
                'registration_date' => $validated['registration_date'] ?? now(),
                'expiration_date' => $validated['expiration_date'],
                'is_active' => true,
            ]);

            // Handle avatar upload if provided
            if ($avatar) {
                $this->mediaService->uploadImage($user, $avatar, 'avatar');
            }

            DB::commit();

            Log::info('User with membership created successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'membership_id' => $membership->id,
                'membership_number' => $membership->membership_number,
            ]);

            return [
                'user' => $user,
                'membership' => $membership,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create user with membership', [
                'email' => $validated['email'] ?? null,
                'membership_number' => $validated['membership_number'] ?? null,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}


