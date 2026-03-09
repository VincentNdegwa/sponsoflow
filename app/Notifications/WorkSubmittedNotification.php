<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\BookingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public BookingSubmission $submission,
        public ?string $reviewUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reviewUrl = $this->reviewUrl ?? route('bookings.show', $this->booking);

        return (new MailMessage)
            ->subject('Your sponsored content is ready to review!')
            ->greeting('Hi '.($notifiable->name ?? $this->booking->guest_name).',')
            ->line('Great news! The creator has submitted work for **'.$this->booking->product->name.'**.')
            ->when($this->submission->work_url, fn ($mail) => $mail->line('**Link:** '.$this->submission->work_url))
            ->action('Review Now', $reviewUrl)
            ->line('You have **72 hours** to approve or request a revision. If we don\'t hear back, the work will be automatically approved.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'work_submitted',
            'booking_id' => $this->booking->id,
            'submission_id' => $this->submission->id,
            'product_name' => $this->booking->product->name,
        ];
    }
}
