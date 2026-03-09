<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSubmission extends Model
{
    protected $fillable = [
        'booking_id',
        'work_url',
        'screenshot_path',
        'revision_notes',
        'revision_number',
        'auto_approve_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_approve_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
