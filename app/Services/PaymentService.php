<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;
use App\Services\Providers\StripePaymentProvider;

class PaymentService
{
    private const string ACTIVE_PROVIDER = 'stripe';

    /**
     * @var array<string, class-string<PaymentProviderInterface>>
     */
    protected array $providers;

    public function __construct()
    {
        $this->providers = [
            'stripe' => StripePaymentProvider::class,
            'paystack' => \App\Services\Providers\PaystackPaymentProvider::class,
        ];
    }

    public function createCheckoutSession(Booking $booking, string $brandCountry = 'global'): array
    {
        $workspace = $booking->product->workspace;
        $paymentConfig = $this->resolveCheckoutPaymentConfiguration($workspace);
        $provider = $this->resolveProviderFromConfig($paymentConfig);

        return $provider->createCheckoutSession($booking, $paymentConfig);
    }

    public function handleSuccessfulPayment(string $paymentReference, string $provider = self::ACTIVE_PROVIDER): void
    {
        $providerInstance = $this->resolveProviderByName($provider);

        $providerInstance->handleSuccessfulPayment($paymentReference);
    }

    public function createConnectAccount(Workspace $workspace, ?string $provider = null, array $bankDetails = []): array
    {
        $providerInstance = $this->resolveProviderForWorkspace($workspace, $provider);

        return $providerInstance->createConnectAccount($workspace, $bankDetails);
    }

    public function updateConnectAccount(Workspace $workspace, ?string $provider = null, array $bankDetails = []): array
    {
        $providerInstance = $this->resolveProviderForWorkspace($workspace, $provider);

        return $providerInstance->updateConnectAccount($workspace, $bankDetails);
    }

    public function getOnboardingUrl(PaymentConfiguration $config): ?string
    {
        if (! $config->provider_account_id) {
            return null;
        }

        $provider = $this->resolveProviderFromConfig($config);

        return $provider->getOnboardingUrl($config);
    }

    public function isAccountVerified(PaymentConfiguration $config): bool
    {
        if (! $config->provider_account_id) {
            return false;
        }

        $provider = $this->resolveProviderFromConfig($config);

        return $provider->isAccountVerified($config);
    }

    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }

    public function getSupportedCountries(string $provider = self::ACTIVE_PROVIDER): array
    {
        $providerInstance = $this->resolveProviderByName($provider);

        return $providerInstance->getSupportedCountries();
    }

    public function getSupportedCurrencies(string $provider = self::ACTIVE_PROVIDER, ?string $countryCode = null): array
    {
        $providerInstance = $this->resolveProviderByName($provider);

        return $providerInstance->getSupportedCurrencies($countryCode);
    }

    public function releaseFunds(Booking $booking): bool
    {
        $workspace = $booking->product->workspace;
        $bookingPayment = $this->resolveLatestBookingPayment($booking);
        $paymentConfig = $this->resolveActivePaymentConfiguration($workspace, $bookingPayment->provider);
        $provider = $this->resolveProviderFromConfig($paymentConfig);

        return $provider->releaseFunds($booking);
    }

    public function refundPayment(Booking $booking, string $reason = 'Work rejected'): bool
    {
        $workspace = $booking->product->workspace;
        $bookingPayment = $this->resolveLatestBookingPayment($booking);
        $paymentConfig = $this->resolveActivePaymentConfiguration($workspace, $bookingPayment->provider);
        $provider = $this->resolveProviderFromConfig($paymentConfig);

        return $provider->refundPayment($booking, $reason);
    }

    public function getSupportedBanks(string $provider = 'paystack', string $countryCode = 'NG', ?string $currency = null, ?string $type = null): array
    {
        $providerInstance = $this->resolveProviderByName($provider);

        return $providerInstance->getSupportedBanks($countryCode, $currency, $type);
    }

    public function verifyBankAccount(string $accountNumber, string $bankCode, string $provider = 'paystack'): array
    {
        $providerInstance = $this->resolveProviderByName($provider);

        return $providerInstance->verifyBankAccount($accountNumber, $bankCode);
    }

    private function resolveProviderFromConfig(PaymentConfiguration $config): PaymentProviderInterface
    {
        return $this->resolveProviderByName($config->provider);
    }

    private function resolveProviderForWorkspace(Workspace $workspace, ?string $provider): PaymentProviderInterface
    {
        $providerName = $provider ?: self::ACTIVE_PROVIDER;
        $this->assertWorkspaceSupportsProvider($workspace, $providerName);

        return $this->resolveProviderByName($providerName);
    }

    private function resolveProviderByName(string $provider): PaymentProviderInterface
    {
        if (! isset($this->providers[$provider])) {
            throw new \Exception("Payment provider '{$provider}' not supported");
        }

        $providerClass = $this->providers[$provider];

        return app($providerClass);
    }

    private function ensureProviderAllowed(string $provider): void {}

    private function assertWorkspaceSupportsProvider(Workspace $workspace, string $provider): void
    {
        if (! $workspace->supportsProvider($provider)) {
            throw new \Exception("Provider '{$provider}' does not support currency '{$workspace->currency}'");
        }
    }

    private function resolveActivePaymentConfiguration(Workspace $workspace, string $provider): PaymentConfiguration
    {
        $paymentConfig = $workspace->activePaymentConfiguration($provider);

        if (! $paymentConfig) {
            throw new \Exception("No active payment configuration found for workspace: {$workspace->name} with provider: {$provider}");
        }

        return $paymentConfig;
    }

    private function resolveCheckoutPaymentConfiguration(Workspace $workspace): PaymentConfiguration
    {
        $config = $workspace->activePaymentConfiguration('stripe');

        if (! $config) {
            $config = $workspace->activePaymentConfiguration('paystack');
        }

        if (! $config) {
            throw new \Exception("No active payment configuration found for workspace: {$workspace->name}");
        }

        return $config;
    }

    private function resolveLatestBookingPayment(Booking $booking): BookingPayment
    {
        $bookingPayment = $booking->latestPayment;

        if (! $bookingPayment) {
            throw new \Exception('No payment record found for booking: '.$booking->id);
        }

        return $bookingPayment;
    }
}
