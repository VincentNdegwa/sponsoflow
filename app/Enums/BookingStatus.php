<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case DISPUTED = 'disputed';
    case INQUIRY = 'inquiry';
    case COUNTER_OFFERED = 'counter_offered';
    case PENDING_PAYMENT = 'pending_payment';
    case REJECTED = 'rejected';
}