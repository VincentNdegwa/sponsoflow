<?php

namespace App\Console\Commands;

use App\Models\Slot;
use Illuminate\Console\Command;

class CleanupExpiredReservations extends Command
{
    protected $signature = 'reservations:cleanup';

    protected $description = 'Clean up expired slot reservations';

    public function handle(): int
    {
        $expiredCount = Slot::expiredReservations()
            ->update([
                'reserved_at' => null,
                'reserved_by' => null,
                'stripe_session_id' => null,
            ]);

        $this->info("Cleaned up {$expiredCount} expired reservations.");

        return 0;
    }
}
