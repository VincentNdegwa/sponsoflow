<?php

namespace App\Enums;

enum BookingType: string
{
    case INSTANT = 'instant';
    case INQUIRY = 'inquiry';
}