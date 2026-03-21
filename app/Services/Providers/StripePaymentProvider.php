<?php

namespace App\Services\Providers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;
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

    public function __construct()
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

    /**
     * Create a checkout session for a booking
     */
    public function createCheckoutSession(Booking $booking, PaymentConfiguration $config): array
    {
        try {
            $workspace = $booking->product->workspace;
            $currency = strtolower($workspace->currency); // Stripe expects lowercase
            $platformFeeAmount = $booking->amount_paid * ($config->platform_fee_percentage / 100);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => $booking->product->name,
                                'description' => "Sponsorship slot on {$booking->slot->slot_date} at {$booking->slot->slot_time}",
                            ],
                            'unit_amount' => $this->convertToSmallestUnit($booking->amount_paid, $currency),
                        ],
                        'quantity' => 1,
                    ],
                ],
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

            // Create payment record
            $payment = BookingPayment::createForBooking($booking, 'stripe', [
                'provider_reference' => $session->id,
                'session_id' => $session->id,
                'currency' => $currency,
                'metadata' => [
                    'workspace_id' => $config->workspace_id,
                    'country' => $workspace->country_code,
                ],
                'provider_data' => [
                    'session_data' => $session->toArray(),
                    'platform_fee_amount' => $platformFeeAmount,
                    'currency' => $currency,
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

    /**
     * Handle successful payment after checkout
     */
    public function handleSuccessfulPayment(string $sessionId): void
    {
        try {
            $session = Session::retrieve($sessionId);

            if ($session->payment_status === 'paid') {
                $payment = BookingPayment::findBySessionId('stripe', $sessionId);

                if ($payment) {
                    $payment->update([
                        'provider_transaction_id' => $session->payment_intent,
                        'status' => 'completed',
                        'paid_at' => now(),
                        'provider_data' => array_merge(
                            $payment->provider_data ?? [],
                            ['payment_intent' => $session->payment_intent]
                        ),
                    ]);

                    // Update booking status
                    $payment->booking->update([
                        'status' => BookingStatus::CONFIRMED,
                    ]);

                    Log::info('Payment confirmed for booking', [
                        'booking_id' => $payment->booking_id,
                        'session_id' => $sessionId,
                        'payment_id' => $payment->id,
                    ]);
                } else {
                    Log::error('Payment record not found for Stripe session', [
                        'session_id' => $sessionId,
                    ]);
                }
            }
        } catch (ApiErrorException $e) {
            Log::error('Failed to handle successful payment', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to process payment confirmation: '.$e->getMessage());
        }
    }

    /**
     * Create a Connect account for accepting payments
     */
    public function createConnectAccount(Workspace $workspace, array $bankDetails = []): array
    {
        try {
            // Map workspace country to Stripe-supported countries
            $stripeCountry = $this->mapToStripeCountry($workspace->country_code);

            $account = Account::create([
                'type' => 'standard',
                'country' => $stripeCountry,
                'business_type' => 'individual', // This should be configurable based on workspace type
            ]);

            // Create or update payment configuration
            PaymentConfiguration::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $workspace->owner()->id,
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

            Log::info('Stripe Connect account created', [
                'workspace_id' => $workspace->id,
                'account_id' => $account->id,
                'country' => $stripeCountry,
            ]);

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

    /**
     * Get onboarding URL for Connect account setup
     */
    public function getOnboardingUrl(PaymentConfiguration $config): ?string
    {
        if (! $config->provider_account_id || $config->is_verified) {
            return null;
        }

        return $this->createOnboardingUrl($config->provider_account_id);
    }

    /**
     * Check if Connect account is fully verified
     */
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

            // Update the config if verification status has changed
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

    /**
     * Create onboarding URL for Connect account
     */
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

    /**
     * Convert amount to smallest currency unit (cents, pence, etc.)
     */
    protected function convertToSmallestUnit(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        if (in_array(strtoupper($currency), $zeroDecimalCurrencies)) {
            return (int) $amount; // No conversion needed for zero-decimal currencies
        }

        return (int) ($amount * 100); // Convert to smallest unit (cents, pence, etc.)
    }

    /**
     * Map workspace country code to Stripe-supported country
     */
    protected function mapToStripeCountry(string $countryCode): string
    {
        $countryMapping = [
            'NG' => 'US', // Nigeria creators can use US Stripe accounts
            'GH' => 'US', // Ghana creators can use US Stripe accounts
            'ZA' => 'US', // South Africa creators can use US Stripe accounts
            'KE' => 'US', // Kenya creators can use US Stripe accounts
            'US' => 'US',
            'CA' => 'CA',
            'GB' => 'GB',
            'DE' => 'DE',
            'FR' => 'FR',
            // Add more mappings as needed
        ];

        return $countryMapping[$countryCode] ?? 'US'; // Default to US
    }
}
