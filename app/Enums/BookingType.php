<?php

namespace App\Enums;

enum BookingType: string
{
    case INSTANT = 'instant';
    case INQUIRY = 'inquiry';

    public function label(): string
    {
        return match($this) {
            self::INSTANT => 'Instant Booking',
            self::INQUIRY => 'Inquiry',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::INSTANT => 'emerald',
            self::INQUIRY => 'blue',
        };
    }
}