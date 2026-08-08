<?php

namespace App\Http\Controllers\Admin\User\Membership\Actions\Update;

use App\Models\User;
use App\Models\Membership;
use App\Jobs\Admin\User\Membership\AdminToMemberMembershipUpdatedNotificationJob;
use Illuminate\Support\Facades\Log;

class SendMembershipUpdateNotificationAction
{
    /**
     * Execute the action to send membership update notification.
     *
     * @param User $user
     * @param Membership $membership
     * @return void
     */
    public function execute(User $user, Membership $membership): void
    {
        try {
            // Dispatch job to send notification (runs asynchronously)
            AdminToMemberMembershipUpdatedNotificationJob::dispatch($user, $membership);

            Log::info('Membership update notification job dispatched', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'membership_id' => $membership->id,
                'membership_number' => $membership->membership_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch membership update notification job', [
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

