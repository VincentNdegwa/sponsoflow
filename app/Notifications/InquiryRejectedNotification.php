<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InquiryRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = $this->booking->guest_name ?? 'there';
        $creatorName = $this->booking->product->workspace->name ?? $this->booking->creator->name;
        $productName = $this->booking->product->name;

        return (new MailMessage)
            ->subject("Update on your inquiry for {$productName}")
            ->greeting("Hi {$recipientName},")
            ->line("Thank you for your interest in collaborating with **{$creatorName}**.")
            ->line("After reviewing your inquiry for **{$productName}**, the creator has decided not to move forward at this time.")
            ->when(! empty($this->booking->creator_notes), fn ($mail) => $mail->line('**Creator note:** '.$this->booking->creator_notes))
            ->line('We encourage you to explore other creators on the platform that may be a great fit for your campaign.');
    }
}
