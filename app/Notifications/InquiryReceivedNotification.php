<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brandName = $this->booking->guest_name ?? $this->booking->guest_email;
        $company = $this->booking->guest_company ? " ({$this->booking->guest_company})" : '';
        $budget = formatMoney($this->booking->amount_paid);

        return (new MailMessage)
            ->subject("New Collaboration Inquiry — {$this->booking->product->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("**{$brandName}{$company}** has sent a collaboration inquiry for **{$this->booking->product->name}**.")
            ->line("**Proposed Budget:** {$budget}")
            ->when(! empty($this->booking->requirement_data['pitch']), fn ($mail) => $mail->line('**Pitch:** '.$this->booking->requirement_data['pitch']))
            ->action('Review Inquiry', route('bookings.show', $this->booking))
            ->line('You can approve, reject, or counter the offer from your bookings dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inquiry_received',
            'booking_id' => $this->booking->id,
            'product_name' => $this->booking->product->name,
            'brand_name' => $this->booking->guest_name ?? $this->booking->guest_email,
            'amount' => $this->booking->amount_paid,
        ];
    }
}
