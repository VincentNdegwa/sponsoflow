<?php

namespace App\Console\Commands;

use App\Models\BookingPayment;
use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class SyncExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rates:sync {--provider= : Preferred provider (frankfurter|exchange_rate_api)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync and cache currency exchange rates to USD';

    /**
     * Execute the console command.
     */
    public function handle(ExchangeRateService $exchangeRateService): int
    {
        $provider = $this->option('provider');

        try {
            $results = $exchangeRateService->syncSupportedRates($provider ?: null);

            if (empty($results)) {
                $this->info('No non-USD currencies configured for sync.');

                return self::SUCCESS;
            }

            foreach ($results as $currency => $rateData) {
                $this->line(sprintf(
                    '%s -> USD = %s (%s)',
                    $currency,
                    number_format((float) $rateData['rate'], 8),
                    $rateData['provider']
                ));
            }


            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Exchange rate sync failed: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
