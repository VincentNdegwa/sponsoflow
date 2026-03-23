<?php

namespace App\Enums;

enum BookingType: string
{
    case INSTANT = 'instant';
    case INQUIRY = 'inquiry';
    case MARKETPLACE_APPLICATION = 'marketplace_application';

    public function label(): string
    {
        return match ($this) {
            self::INSTANT => 'Instant Booking',
            self::INQUIRY => 'Inquiry',
            self::MARKETPLACE_APPLICATION => 'Marketplace Match',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::INSTANT => 'emerald',
            self::INQUIRY => 'blue',
            self::MARKETPLACE_APPLICATION => 'amber',
        };
    }
}
