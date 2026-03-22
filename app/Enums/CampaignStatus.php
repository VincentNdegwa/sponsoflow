<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Paused => 'Paused',
            self::Closed => 'Closed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Published => 'blue',
            self::Paused => 'amber',
            self::Closed => 'green',
        };
    }

    public function isMarketplaceVisible(): bool
    {
        return in_array($this, [self::Published, self::Paused], true);
    }

    public function canAcceptApplications(): bool
    {
        return $this === self::Published;
    }
}
