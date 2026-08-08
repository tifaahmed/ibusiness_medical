<?php

namespace App\Jobs\Admin\User\Membership;

use App\Models\User;
use App\Models\Membership;
use App\Notifications\AdminToMember\AdminToMemberMembershipUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AdminToMemberMembershipUpdatedNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Membership $membership
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->user->notify(new AdminToMemberMembershipUpdatedNotification($this->membership));

            Log::info('Membership update notification sent successfully', [
                'user_id' => $this->user->id,
                'user_email' => $this->user->email,
                'membership_id' => $this->membership->id,
                'membership_number' => $this->membership->membership_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send membership update notification', [
                'user_id' => $this->user->id,
                'user_email' => $this->user->email,
                'membership_id' => $this->membership->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}


