<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brandName = $this->booking->brandUser?->name ?? $this->booking->guest_name ?? 'The brand';

        return (new MailMessage)
            ->subject('Work approved — payment is on its way!')
            ->greeting('Hi '.$notifiable->name.',')
            ->line($brandName.' has approved your submission for **'.$this->booking->product->name.'**.')
            ->line('The payment of **'.formatMoney($this->booking->amount_paid).'** has been released from escrow and will be transferred to your account.')
            ->action('View Booking', route('bookings.show', $this->booking));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'work_approved',
            'booking_id' => $this->booking->id,
            'product_name' => $this->booking->product->name,
            'amount_paid' => $this->booking->amount_paid,
        ];
    }
}
