<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Membership;
use App\Notifications\MembershipCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendMembershipNotificationJob implements ShouldQueue
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
     * @var array<int>
     */
    public $backoff = [30, 60, 120];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public Membership $membership
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->user->notify(new MembershipCreatedNotification($this->membership));

            Log::info('Membership notification sent successfully', [
                'user_id' => $this->user->id,
                'user_email' => $this->user->email,
                'membership_id' => $this->membership->id,
                'membership_number' => $this->membership->membership_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send membership notification', [
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
