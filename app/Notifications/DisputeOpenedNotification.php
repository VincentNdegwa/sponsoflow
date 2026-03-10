<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisputeOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Dispute opened for booking #'.$this->booking->id)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('A dispute has been opened for the booking of **'.$this->booking->product->name.'**.')
            ->line('**Reason:** '.$this->reason)
            ->line('Our team will review the case and reach out within 48 hours. The payment remains in escrow until the dispute is resolved.')
            ->action('View Booking', route('bookings.show', $this->booking));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'dispute_opened',
            'booking_id' => $this->booking->id,
            'product_name' => $this->booking->product->name,
            'reason' => $this->reason,
        ];
    }
}
