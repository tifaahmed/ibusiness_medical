<?php

namespace App\Notifications\AdminToMember;

use App\Models\Membership;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminToMemberMembershipUpdatedNotification extends Notification
{

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Membership $membership
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable)
    {
        return (new \App\Mail\MembershipUpdatedMail($notifiable, $this->membership));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'membership_id' => $this->membership->id,
            'membership_number' => $this->membership->membership_number,
            'action' => 'updated',
        ];
    }
}


