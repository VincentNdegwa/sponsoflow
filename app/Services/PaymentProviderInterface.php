<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;

interface PaymentProviderInterface
{
    /**
     * Create a checkout session for a booking
     */
    public function createCheckoutSession(Booking $booking, PaymentConfiguration $config): array;

    /**
     * Handle successful payment after checkout
     */
    public function handleSuccessfulPayment(string $sessionId): void;

    /**
     * Create a Connect account for accepting payments
     */
    public function createConnectAccount(Workspace $workspace, array $bankDetails = []): array;

    /**
     * Get onboarding URL for Connect account setup
     */
    public function getOnboardingUrl(PaymentConfiguration $config): ?string;

    /**
     * Check if Connect account is fully verified
     */
    public function isAccountVerified(PaymentConfiguration $config): bool;
}
