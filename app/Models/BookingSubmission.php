<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSubmission extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'uuid',
        'booking_id',
        'work_url',
        'screenshot_path',
        'revision_notes',
        'revision_number',
        'auto_approve_at',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

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
