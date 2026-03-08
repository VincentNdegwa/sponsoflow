<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseEscrowFunds extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payments:release-escrow 
                           {--hours=72 : Hours after confirmation to auto-release funds}
                           {--dry-run : Show what would be released without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically release escrow funds for confirmed bookings after specified time period';

    public function __construct(private PaymentService $paymentService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->option('hours');
        $isDryRun = $this->option('dry-run');
        
        $this->info("Looking for confirmed bookings older than {$hours} hours...");
        
        // Find confirmed bookings with successful Paystack payments older than specified hours
        $eligibleBookings = Booking::query()
            ->where('status', BookingStatus::CONFIRMED)
            ->whereHas('payments', function ($query) {
                $query->where('provider', 'paystack')
                      ->where('status', 'completed');
            })
            ->whereHas('payments', function ($query) use ($hours) {
                $query->where('paid_at', '<=', now()->subHours($hours));
            })
            // Ensure funds haven't been released yet (you might want to add a released_at field)
            ->whereDoesntHave('payments', function ($query) {
                $query->where('provider_data->funds_released', true);
            })
            ->get();

        if ($eligibleBookings->isEmpty()) {
            $this->info('No bookings found that are eligible for automatic fund release.');
            return self::SUCCESS;
        }

        $this->info("Found {$eligibleBookings->count()} bookings eligible for fund release:");

        $successCount = 0;
        $failureCount = 0;

        foreach ($eligibleBookings as $booking) {
            $payment = $booking->getPaymentFor('paystack');
            $hoursOld = $payment->paid_at->diffInHours(now());
            
            $this->line("- Booking #{$booking->id}: Paid {$hoursOld} hours ago (Amount: {$booking->amount_paid})");
            
            if ($isDryRun) {
                $this->info("  [DRY RUN] Would release funds for booking #{$booking->id}");
                continue;
            }

            try {
                $success = $this->paymentService->releaseFunds($booking);
                
                if ($success) {
                    // Mark as released in payment data
                    $payment->update([
                        'provider_data' => array_merge(
                            $payment->provider_data ?? [],
                            [
                                'funds_released' => true,
                                'auto_released_at' => now()->toISOString(),
                                'release_reason' => "Auto-released after {$hours} hours",
                            ]
                        ),
                    ]);
                    
                    $this->info("  ✅ Successfully released funds for booking #{$booking->id}");
                    $successCount++;
                    
                    Log::info('Auto-released escrow funds', [
                        'booking_id' => $booking->id,
                        'amount' => $booking->amount_paid,
                        'hours_elapsed' => $hoursOld,
                    ]);
                } else {
                    $this->error("  ❌ Failed to release funds for booking #{$booking->id}");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Error releasing funds for booking #{$booking->id}: {$e->getMessage()}");
                $failureCount++;
                
                Log::error('Auto-release fund error', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$isDryRun) {
            $this->info("\nSummary:");
            $this->info("✅ Successfully released: {$successCount}");
            if ($failureCount > 0) {
                $this->warn("❌ Failed: {$failureCount}");
            }
        }

        return self::SUCCESS;
    }
}
