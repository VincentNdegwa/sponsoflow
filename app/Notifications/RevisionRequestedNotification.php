<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\BookingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevisionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public BookingSubmission $submission,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brandName = $this->booking->brandUser?->name ?? $this->booking->guest_name ?? 'The brand';
        $revisionsLeft = $this->booking->max_revisions - $this->booking->revision_count;

        return (new MailMessage)
            ->subject('Revision requested for '.$this->booking->product->name)
            ->greeting('Hi '.$notifiable->name.',')
            ->line($brandName.' has requested a revision for **'.$this->booking->product->name.'**.')
            ->line('**What they need changed:**')
            ->line($this->submission->revision_notes)
            ->line('Revisions remaining after this: **'.$revisionsLeft.'**')
            ->action('View Booking', route('bookings.show', $this->booking));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'revision_requested',
            'booking_id' => $this->booking->id,
            'product_name' => $this->booking->product->name,
            'revision_notes' => $this->submission->revision_notes,
            'revision_number' => $this->booking->revision_count,
        ];
    }
}
