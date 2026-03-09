<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InquiryCounteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $respondUrl,
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
        $originalAmount = formatMoney($this->booking->amount_paid);
        $counterAmount = formatMoney($this->booking->counter_amount);

        return (new MailMessage)
            ->subject("{$creatorName} has a counter-offer for you")
            ->greeting("Hi {$recipientName},")
            ->line("**{$creatorName}** has reviewed your inquiry for **{$productName}** and would like to propose a counter-offer.")
            ->line("**Your proposed budget:** {$originalAmount}")
            ->line("**Creator's counter-offer:** {$counterAmount}")
            ->when(! empty($this->booking->creator_notes), fn ($mail) => $mail->line('**Message from creator:** '.$this->booking->creator_notes))
            ->action('Review Counter-Offer', $this->respondUrl)
            ->line('You can accept or decline this counter-offer. This link is valid for 14 days.');
    }
}
