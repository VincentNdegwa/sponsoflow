<?php

namespace App\Services\Providers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;
use App\Services\PaymentCompletionService;
use App\Services\PaymentProviderInterface;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripePaymentProvider implements PaymentProviderInterface
{
    protected const array SUPPORTED_COUNTRIES = [
        'US' => ['name' => 'United States', 'default_currency_code' => 'USD', 'currencies' => ['USD']],
        'CA' => ['name' => 'Canada', 'default_currency_code' => 'CAD', 'currencies' => ['CAD', 'USD']],
        'GB' => ['name' => 'United Kingdom', 'default_currency_code' => 'GBP', 'currencies' => ['GBP']],
        'DE' => ['name' => 'Germany', 'default_currency_code' => 'EUR', 'currencies' => ['EUR']],
        'FR' => ['name' => 'France', 'default_currency_code' => 'EUR', 'currencies' => ['EUR']],
    ];

    public function __construct(protected PaymentCompletionService $paymentCompletionService)
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function getSupportedCountries(): array
    {
        return collect(self::SUPPORTED_COUNTRIES)
            ->map(fn (array $data, string $code): array => [
                'iso_code' => $code,
                'name' => $data['name'],
                'default_currency_code' => $data['default_currency_code'],
                'currencies' => $data['currencies'],
            ])
            ->values()
            ->all();
    }

    public function getSupportedCurrencies(?string $countryCode = null): array
    {
        if ($countryCode && isset(self::SUPPORTED_COUNTRIES[$countryCode])) {
            return self::SUPPORTED_COUNTRIES[$countryCode]['currencies'];
        }

        return collect(self::SUPPORTED_COUNTRIES)
            ->flatMap(fn (array $data): array => $data['currencies'])
            ->unique()
            ->values()
            ->all();
    }

    public function createCheckoutSession(Booking $booking, PaymentConfiguration $config): array
    {
        try {
            $workspace = $booking->product->workspace;
            $currency = strtolower($workspace->currency);
            $platformFeeAmount = $booking->amount_paid * ($config->platform_fee_percentage / 100);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $booking->product->name,
                            'description' => 'Sponsorship booking #'.$booking->id,
                        ],
                        'unit_amount' => $this->convertToSmallestUnit($booking->amount_paid, $currency),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.success', ['session_id' => '{CHECKOUT_SESSION_ID}']),
                'cancel_url' => route('payment.cancel'),
                'payment_intent_data' => [
                    'application_fee_amount' => $this->convertToSmallestUnit($platformFeeAmount, $currency),
                    'transfer_data' => [
                        'destination' => $config->provider_account_id,
                    ],
                ],
                'metadata' => [
                    'booking_id' => $booking->id,
                    'workspace_id' => $config->workspace_id,
                    'currency' => $currency,
                    'country' => $workspace->country_code,
                ],
            ]);

            $payment = BookingPayment::createForBooking($booking, 'stripe', [
                'provider_reference' => $session->id,
                'session_id' => $session->id,
                'currency' => strtoupper($currency),
                'metadata' => [
                    'workspace_id' => $config->workspace_id,
                    'country' => $workspace->country_code,
                ],
                'provider_data' => [
                    'session_data' => $session->toArray(),
                    'platform_fee_amount' => $platformFeeAmount,
                    'currency' => strtoupper($currency),
                ],
            ]);

            return [
                'id' => $session->id,
                'url' => $session->url,
                'payment_id' => $payment->id,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout session creation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment session: '.$e->getMessage());
        }
    }

    public function handleSuccessfulPayment(string $paymentReference): void
    {
        try {
            $session = Session::retrieve($paymentReference);

            if ($session->payment_status !== 'paid') {
                return;
            }

            $payment = BookingPayment::query()
                ->with(['booking.slot', 'booking.product', 'booking.creator', 'booking.campaignSlot'])
                ->where('provider', 'stripe')
                ->where('session_id', $paymentReference)
                ->first();

            if (! $payment) {
                Log::error('Payment record not found for Stripe session', [
                    'session_id' => $paymentReference,
                ]);

                return;
            }

            $currency = strtoupper((string) ($session->currency ?? $payment->currency ?? 'USD'));
            $amountTotal = (int) ($session->amount_total ?? 0);
            $paidAmount = $amountTotal > 0
                ? $this->convertFromSmallestUnit($amountTotal, $currency)
                : (float) $payment->amount;
            $localBreakdown = $this->buildLocalBreakdown($payment, $paidAmount, $currency);
            $conversion = $this->paymentCompletionService->normalizeUsdConversion(
                $this->paymentCompletionService->resolveUsdConversion($payment, $paidAmount, $paymentReference),
                $paidAmount
            );

            $paymentUpdate = [
                'provider_transaction_id' => $session->payment_intent,
                'status' => 'completed',
                'paid_at' => now(),
                'amount' => $paidAmount,
                'amount_usd' => $conversion['amount_usd'],
                'exchange_rate_to_usd' => $conversion['exchange_rate_to_usd'],
                'exchange_rate_provider' => $conversion['provider'],
                'exchange_rate_fetched_at' => $conversion['fetched_at'],
                'amount_breakdown' => [
                    'local' => $localBreakdown,
                    'usd' => $this->paymentCompletionService->convertBreakdownToUsd(
                        $localBreakdown,
                        (float) ($conversion['exchange_rate_to_usd'] ?? 0)
                    ),
                ],
                'provider_data' => array_merge(
                    $payment->provider_data ?? [],
                    [
                        'payment_intent' => $session->payment_intent,
                        'session_data' => $session->toArray(),
                        'verified_at' => now()->toISOString(),
                    ]
                ),
            ];

            $this->paymentCompletionService->finalizePayment($payment, $paymentUpdate, [
                'session_id' => $paymentReference,
                'payment_intent' => $session->payment_intent,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed to handle successful Stripe payment', [
                'session_id' => $paymentReference,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to process payment confirmation: '.$e->getMessage());
        }
    }

    public function createConnectAccount(Workspace $workspace, array $bankDetails = []): array
    {
        try {
            $stripeCountry = $this->mapToStripeCountry($workspace->country_code);

            $account = Account::create([
                'type' => 'standard',
                'country' => $stripeCountry,
                'business_type' => 'individual',
            ]);

            PaymentConfiguration::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $workspace->owner_id,
                    'provider' => 'stripe',
                ],
                [
                    'provider_account_id' => $account->id,
                    'is_active' => true,
                    'is_verified' => false,
                    'provider_data' => [
                        'country' => $stripeCountry,
                        'currency' => $workspace->currency,
                        'business_type' => 'individual',
                    ],
                ]
            );

            return [
                'account_id' => $account->id,
                'onboarding_url' => $this->createOnboardingUrl($account->id),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe Connect account', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment account: '.$e->getMessage());
        }
    }

    public function updateConnectAccount(Workspace $workspace, array $bankDetails = []): array
    {
        throw new \Exception('Stripe provider does not support connect-account updates via this flow.');
    }

    public function getOnboardingUrl(PaymentConfiguration $config): ?string
    {
        if (! $config->provider_account_id || $config->is_verified) {
            return null;
        }

        return $this->createOnboardingUrl($config->provider_account_id);
    }

    public function isAccountVerified(PaymentConfiguration $config): bool
    {
        if (! $config->provider_account_id) {
            return false;
        }

        try {
            $account = Account::retrieve($config->provider_account_id);

            $isVerified = $account->charges_enabled &&
                $account->payouts_enabled &&
                ! $account->requirements->eventually_due;

            if ($isVerified && ! $config->is_verified) {
                $config->markAsVerified();
            }

            return $isVerified;
        } catch (ApiErrorException $e) {
            Log::error('Failed to check Stripe account verification', [
                'config_id' => $config->id,
                'account_id' => $config->provider_account_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function releaseFunds(Booking $booking): bool
    {
        throw new \Exception('Stripe provider does not support manual fund release in this integration.');
    }

    public function refundPayment(Booking $booking, string $reason = 'Work rejected'): bool
    {
        throw new \Exception('Stripe provider does not support refunds in this integration.');
    }

    public function getSupportedBanks(string $countryCode = 'NG', ?string $currency = null, ?string $type = null): array
    {
        return [];
    }

    public function verifyBankAccount(string $accountNumber, string $bankCode): array
    {
        throw new \Exception('Stripe provider does not support bank verification in this integration.');
    }

    protected function createOnboardingUrl(string $accountId): string
    {
        try {
            $accountLink = AccountLink::create([
                'account' => $accountId,
                'refresh_url' => route('stripe.connect.refresh'),
                'return_url' => route('stripe.connect.return'),
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe onboarding URL', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create onboarding URL: '.$e->getMessage());
        }
    }

    protected function convertToSmallestUnit(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies, true)) {
            return (int) $amount;
        }

        return (int) ($amount * 100);
    }

    protected function convertFromSmallestUnit(int $amount, string $currency): float
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies, true)) {
            return (float) $amount;
        }

        return $amount / 100;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLocalBreakdown(BookingPayment $payment, float $paidAmount, string $currency): array
    {
        $platformFeeAmount = (float) data_get($payment->provider_data, 'platform_fee_amount', 0);
        $escrowAmount = max(round($paidAmount - $platformFeeAmount, 2), 0);

        return [
            'currency' => $currency,
            'gross_amount' => $paidAmount,
            'platform_fee_amount' => $platformFeeAmount,
            'processor_fee_amount' => null,
            'escrow_amount' => $escrowAmount,
            'creator_payout_amount' => $escrowAmount,
            'requested_amount' => null,
        ];
    }

    protected function mapToStripeCountry(string $countryCode): string
    {
        $countryMapping = [
            'NG' => 'US',
            'GH' => 'US',
            'ZA' => 'US',
            'KE' => 'US',
            'US' => 'US',
            'CA' => 'CA',
            'GB' => 'GB',
            'DE' => 'DE',
            'FR' => 'FR',
        ];

        return $countryMapping[$countryCode] ?? 'US';
    }
}
