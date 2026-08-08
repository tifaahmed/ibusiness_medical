<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions;

use App\Models\User;
use App\Models\Membership;
use App\Jobs\Admin\User\Membership\SendMembershipNotificationJob;
use Illuminate\Support\Facades\Log;

class AdminToMemberMembershipCreatedNotificationJob
{
    /**
     * Execute the action to send membership notification.
     *
     * @param User $user
     * @param Membership $membership
     * @return void
     */
    public function execute(User $user, Membership $membership): void
    {
        try {
            // Dispatch job to send notification (runs asynchronously)
            SendMembershipNotificationJob::dispatch($user, $membership);

            Log::info('Membership notification job dispatched', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'membership_id' => $membership->id,
                'membership_number' => $membership->membership_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch membership notification job', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'membership_id' => $membership->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            // Don't throw exception - notification failure shouldn't break the flow
            // The job will be retried automatically if it fails
        }
    }
}


