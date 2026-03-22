<?php

namespace App\Enums;

enum CampaignApplicationStatus: string
{
    case Submitted = 'submitted';
    case Rejected = 'rejected';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Rejected => 'Rejected',
            self::Approved => 'Approved',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Submitted => 'amber',
            self::Rejected => 'red',
            self::Approved => 'green',
        };
    }
}
