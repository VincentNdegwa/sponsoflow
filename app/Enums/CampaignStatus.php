<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Completed => 'Completed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Pending => 'amber',
            self::Active => 'blue',
            self::Completed => 'green',
        };
    }
}
