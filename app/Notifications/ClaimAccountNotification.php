<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ClaimAccountResetUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClaimAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;

    public Workspace $workspace;

    public function __construct(Booking $booking, Workspace $workspace)
    {
        $this->booking = $booking;
        $this->workspace = $workspace;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $notifiable instanceof User
            ? ClaimAccountResetUrl::resolveFor($notifiable, $this->booking->fresh())
            : null;

        return (new MailMessage)
            ->subject('Claim Your SponsorFlow Account - Payment Successful!')
            ->markdown('emails.claim-account', [
                'user' => $notifiable,
                'booking' => $this->booking,
                'workspace' => $this->workspace,
                'url' => $url,
                'creator_name' => $this->booking->product->workspace->name,
                'product_name' => $this->booking->product->name,
                'amount_paid' => formatMoney($this->booking->amount_paid),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'workspace_id' => $this->workspace->id,
            'product_name' => $this->booking->product->name,
            'amount_paid' => $this->booking->amount_paid,
        ];
    }
}
