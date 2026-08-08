<?php

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipCreatedNotification extends Notification
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
        $channels = [];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome! Your Membership Has Been Created')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your membership has been successfully created.')
            ->line('**Membership Number:** ' . $this->membership->membership_number)
            ->line('**Registration Date:** ' . $this->membership->registration_date->format('F d, Y'))
            ->line('**Expiration Date:** ' . $this->membership->expiration_date->format('F d, Y'))
            ->line('Thank you for joining us!')
            ->action('View Your Membership', url('/'));
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
        ];
    }
}
