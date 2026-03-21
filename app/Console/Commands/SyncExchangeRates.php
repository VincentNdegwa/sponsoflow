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

            BookingPayment::query()
                ->where('status', 'completed')
                ->whereNull('amount_usd')
                ->orderBy('id')
                ->chunkById(100, function ($payments) use ($exchangeRateService): void {
                    foreach ($payments as $payment) {
                        $conversion = $exchangeRateService->convertToUsd((float) $payment->amount, $payment->currency);
                        $amountBreakdown = $payment->amount_breakdown ?? [];

                        if (! data_get($amountBreakdown, 'local.currency')) {
                            data_set($amountBreakdown, 'local.currency', $payment->currency);
                        }

                        if (! data_get($amountBreakdown, 'local.gross_amount')) {
                            data_set($amountBreakdown, 'local.gross_amount', (float) $payment->amount);
                        }

                        data_set($amountBreakdown, 'usd.currency', 'USD');
                        data_set($amountBreakdown, 'usd.gross_amount', $conversion['amount_usd']);

                        $payment->update([
                            'amount_usd' => $conversion['amount_usd'],
                            'exchange_rate_to_usd' => $conversion['exchange_rate_to_usd'],
                            'exchange_rate_provider' => $conversion['provider'],
                            'exchange_rate_fetched_at' => $conversion['fetched_at'],
                            'amount_breakdown' => $amountBreakdown,
                        ]);
                    }
                });

            $this->info('Exchange rates synced successfully.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Exchange rate sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
