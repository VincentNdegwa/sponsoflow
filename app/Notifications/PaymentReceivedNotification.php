<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brandName = $this->booking->guest_name ?? $this->booking->brandUser?->name ?? $this->booking->guest_email;
        $amount = formatMoney($this->booking->amount_paid);
        $productName = $this->booking->product->name;

        return (new MailMessage)
            ->subject("Payment Received — {$productName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("**{$brandName}** has completed payment of **{$amount}** for **{$productName}**.")
            ->line('The booking is now confirmed and you can begin working on the deliverable.')
            ->action('View Booking & Submit Work', route('bookings.show', $this->booking))
            ->line('Once your work is ready, submit it through your bookings dashboard for the brand to review.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_received',
            'booking_id' => $this->booking->id,
            'product_name' => $this->booking->product->name,
            'brand_name' => $this->booking->guest_name ?? $this->booking->brandUser?->name ?? $this->booking->guest_email,
            'amount' => $this->booking->amount_paid,
        ];
    }
}
