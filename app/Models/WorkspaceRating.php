<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceRating extends Model
{
    protected $fillable = [
        'workspace_id',
        'booking_id',
        'booking_submission_id',
        'rating',
        'tags',
        'comment',
        'rated_by_guest_email',
        'rated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'rating' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingSubmission(): BelongsTo
    {
        return $this->belongsTo(BookingSubmission::class);
    }

    public function ratedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by_user_id');
    }
}
