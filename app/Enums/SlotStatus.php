<?php

namespace App\Enums;

enum SlotStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Booked = 'booked';
    case Processing = 'processing';
    case Completed = 'completed';

    public function label(): string
    {
        return match($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved (Unpaid)',
            self::Booked => 'Booked (Paid)',
            self::Processing => 'Processing (Assets Uploaded)', 
            self::Completed => 'Completed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Available => 'green',
            self::Reserved => 'yellow',
            self::Booked => 'blue',
            self::Processing => 'purple',
            self::Completed => 'gray',
        };
    }

    public function canTransitionTo(SlotStatus $status): bool
    {
        return match($this) {
            self::Available => in_array($status, [self::Reserved]),
            self::Reserved => in_array($status, [self::Booked, self::Available]),
            self::Booked => in_array($status, [self::Processing]),
            self::Processing => in_array($status, [self::Completed]),
            self::Completed => false,
        };
    }
}
