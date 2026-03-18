<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;
use App\Services\Providers\StripePaymentProvider;

class PaymentService
{
    private const string ACTIVE_PROVIDER = 'paystack';

    protected array $providers = [];

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
        $paymentConfig = $workspace->activePaymentConfiguration(self::ACTIVE_PROVIDER);

        if (! $paymentConfig) {
            throw new \Exception("No active payment configuration found for workspace: {$workspace->name} with provider: ".self::ACTIVE_PROVIDER);
        }

        $provider = $this->getProvider($paymentConfig);

        return $provider->createCheckoutSession($booking, $paymentConfig);
    }

    public function handleSuccessfulPayment(string $sessionId, string $provider = self::ACTIVE_PROVIDER): void
    {
        $providerInstance = $this->getProviderByName($provider);
        $providerInstance->handleSuccessfulPayment($sessionId);
    }

    public function createConnectAccount(Workspace $workspace, ?string $provider = null, array $bankDetails = []): array
    {
        // Auto-select best provider if not specified
        if (! $provider) {
            $provider = self::ACTIVE_PROVIDER;
        }

        $this->ensureProviderAllowed($provider);

        // Validate provider supports workspace currency
        if (! $workspace->supportsProvider($provider)) {
            throw new \Exception("Provider '{$provider}' does not support currency '{$workspace->currency}'");
        }

        $providerInstance = $this->getProviderByName($provider);

        return $providerInstance->createConnectAccount($workspace, $bankDetails);
    }

    public function getOnboardingUrl(PaymentConfiguration $config): ?string
    {
        if (! $config->provider_account_id) {
            return null;
        }

        $provider = $this->getProvider($config);

        return $provider->getOnboardingUrl($config);
    }

    public function isAccountVerified(PaymentConfiguration $config): bool
    {
        if (! $config->provider_account_id) {
            return false;
        }

        $provider = $this->getProvider($config);

        return $provider->isAccountVerified($config);
    }

    protected function getProvider(PaymentConfiguration $config): PaymentProviderInterface
    {
        return $this->getProviderByName($config->provider);
    }

    protected function getProviderByName(string $provider): PaymentProviderInterface
    {
        if (! isset($this->providers[$provider])) {
            throw new \Exception("Payment provider '{$provider}' not supported");
        }

        $providerClass = $this->providers[$provider];

        return app($providerClass);
    }

    public function getAvailableProviders(): array
    {
        return [self::ACTIVE_PROVIDER];
    }

    public function releaseFunds(Booking $booking): bool
    {
        $workspace = $booking->product->workspace;
        $booking_payment = $booking->latestPayment;
        if (! $booking_payment) {
            throw new \Exception('No payment record found for booking: '.$booking->id);
        }
        $paymentConfig = $workspace->activePaymentConfiguration($booking_payment->provider);

        if (! $paymentConfig) {
            throw new \Exception('No active payment configuration found for workspace: '.$workspace->name);
        }

        $provider = $this->getProvider($paymentConfig);

        if (method_exists($provider, 'releaseFunds')) {
            return $provider->releaseFunds($booking);
        }

        throw new \Exception("Provider '{$paymentConfig->provider}' does not support fund release");
    }

    public function refundPayment(Booking $booking, string $reason = 'Work rejected'): bool
    {
        $workspace = $booking->product->workspace;
        $booking_payment = $booking->latestPayment;
        if (! $booking_payment) {
            throw new \Exception('No payment record found for booking: '.$booking->id);
        }
        $paymentConfig = $workspace->activePaymentConfiguration($booking_payment->provider);

        if (! $paymentConfig) {
            throw new \Exception('No active payment configuration found for workspace: '.$workspace->name);
        }

        $provider = $this->getProvider($paymentConfig);

        if (method_exists($provider, 'refundPayment')) {
            return $provider->refundPayment($booking, $reason);
        }

        throw new \Exception("Provider '{$paymentConfig->provider}' does not support refunds");
    }

    public function getSupportedBanks(string $provider = 'paystack', string $countryCode = 'NG'): array
    {
        $this->ensureProviderAllowed($provider);

        $providerInstance = $this->getProviderByName($provider);

        if (method_exists($providerInstance, 'getSupportedBanks')) {
            return $providerInstance->getSupportedBanks($countryCode);
        }

        return [];
    }

    public function verifyBankAccount(string $accountNumber, string $bankCode, string $provider = 'paystack'): array
    {
        $this->ensureProviderAllowed($provider);

        $providerInstance = $this->getProviderByName($provider);

        if (method_exists($providerInstance, 'verifyBankAccount')) {
            return $providerInstance->verifyBankAccount($accountNumber, $bankCode);
        }

        throw new \Exception("Provider '{$provider}' does not support bank verification");
    }

    private function ensureProviderAllowed(string $provider): void
    {
        if ($provider !== self::ACTIVE_PROVIDER) {
            throw new \Exception("Provider '{$provider}' is currently disabled. Active provider: ".self::ACTIVE_PROVIDER);
        }
    }
}
