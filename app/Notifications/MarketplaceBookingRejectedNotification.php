<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceBookingRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $this->booking->guest_name ?? $this->booking->brandUser?->name ?? 'there';
        $creatorName = $this->booking->product->workspace->name ?? $this->booking->creator->name;
        $productName = $this->booking->product->name;

        return (new MailMessage)
            ->subject("Update on your campaign application to {$creatorName}")
            ->greeting("Hi {$recipientName},")
            ->line("Your marketplace collaboration request for **{$productName}** was not accepted by **{$creatorName}**.")
            ->line('You can apply to other creators in the marketplace anytime.');
    }
}
