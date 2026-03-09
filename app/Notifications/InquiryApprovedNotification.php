<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InquiryApprovedNotification extends Notification implements ShouldQueue
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
        $recipientName = $this->booking->guest_name ?? 'there';
        $creatorName = $this->booking->product->workspace->name ?? $this->booking->creator->name;
        $productName = $this->booking->product->name;
        $amount = formatMoney($this->booking->amount_paid);

        return (new MailMessage)
            ->subject("Great news! Your inquiry has been approved by {$creatorName}")
            ->greeting("Hi {$recipientName},")
            ->line("**{$creatorName}** has approved your inquiry for **{$productName}**.")
            ->line("To confirm your booking, please complete the project requirements and payment of **{$amount}**.")
            ->action('Complete Your Booking', $this->checkoutUrl)
            ->line('This link is valid for 14 days. Complete your booking to secure your spot.');
    }
}
