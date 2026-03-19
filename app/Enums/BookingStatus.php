<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case REVISION_REQUESTED = 'revision_requested';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';
    case INQUIRY = 'inquiry';
    case COUNTER_OFFERED = 'counter_offered';
    case PENDING_PAYMENT = 'pending_payment';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::PROCESSING => 'Processing',
            self::REVISION_REQUESTED => 'Revision Requested',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::DISPUTED => 'Disputed',
            self::INQUIRY => 'Inquiry',
            self::COUNTER_OFFERED => 'Counter Offered',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::REJECTED => 'Rejected',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::CONFIRMED => 'lime',
            self::PROCESSING => 'orange',
            self::REVISION_REQUESTED => 'fuchsia',
            self::COMPLETED => 'green',
            self::CANCELLED => 'zinc',
            self::DISPUTED => 'red',
            self::INQUIRY => 'sky',
            self::COUNTER_OFFERED => 'purple',
            self::PENDING_PAYMENT => 'yellow',
            self::REJECTED => 'rose',
        };
    }
}
