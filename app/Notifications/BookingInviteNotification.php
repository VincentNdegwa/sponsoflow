<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $inviteUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $creatorName = $this->booking->product?->workspace?->name ?? $this->booking->creator?->name ?? 'A creator';
        $recipientName = $notifiable->name ?? $this->booking->guest_name ?? 'there';
        $productName = $this->booking->product?->name ?? 'a collaboration';
        $amount = formatMoney($this->booking->amount_paid);

        return (new MailMessage)
            ->subject("{$creatorName} has sent you a collaboration proposal")
            ->greeting("Hi {$recipientName},")
            ->line("**{$creatorName}** has created a collaboration booking for **{$productName}** and is ready for you to review and complete payment.")
            ->line("**Amount:** {$amount}")
            ->when($this->booking->notes, fn ($mail) => $mail->line("**Note from creator:** {$this->booking->notes}"))
            ->action('Review & Complete Payment', $this->inviteUrl)
            ->line('This link is valid for 30 days. Click above to review the details and secure your booking.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'booking_invite',
            'booking_id' => $this->booking->id,
            'product_name' => $this->booking->product?->name,
            'creator_name' => $this->booking->product?->workspace?->name ?? $this->booking->creator?->name,
            'amount' => $this->booking->amount_paid,
            'invite_url' => $this->inviteUrl,
        ];
    }
}
