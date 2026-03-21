<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;

interface PaymentProviderInterface
{
    public function getSupportedCountries(): array;

    public function getSupportedCurrencies(?string $countryCode = null): array;

    public function createCheckoutSession(Booking $booking, PaymentConfiguration $config): array;

    public function handleSuccessfulPayment(string $paymentReference): void;

    public function createConnectAccount(Workspace $workspace, array $bankDetails = []): array;

    public function updateConnectAccount(Workspace $workspace, array $bankDetails = []): array;

    public function getOnboardingUrl(PaymentConfiguration $config): ?string;

    public function isAccountVerified(PaymentConfiguration $config): bool;

    public function releaseFunds(Booking $booking): bool;

    public function refundPayment(Booking $booking, string $reason = 'Work rejected'): bool;

    public function getSupportedBanks(string $countryCode = 'NG', ?string $currency = null, ?string $type = null): array;

    public function verifyBankAccount(string $accountNumber, string $bankCode): array;
}
