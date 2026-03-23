<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketplaceBookingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $checkoutUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $this->booking->guest_name ?? $this->booking->brandUser?->name ?? 'there';
        $creatorName = $this->booking->product->workspace->name ?? $this->booking->creator->name;
        $productName = $this->booking->product->name;
        $amount = formatMoney((float) $this->booking->amount_paid, $this->booking->brandWorkspace, $this->booking->currency);

        return (new MailMessage)
            ->subject("Your campaign application was accepted by {$creatorName}")
            ->greeting("Hi {$recipientName},")
            ->line("Great news: **{$creatorName}** accepted your marketplace collaboration request for **{$productName}**.")
            ->line("Complete payment of **{$amount}** to lock this collaboration.")
            ->action('Complete Payment', $this->checkoutUrl)
            ->line('This link is valid for 14 days.');
    }
}
