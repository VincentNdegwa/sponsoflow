<?php

namespace App\Livewire\Concerns;

use App\Models\PaymentConfiguration;
use App\Models\Workspace;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

trait HandlesStripePaymentSetup
{
    public string $provider = 'stripe';

    public bool $is_connecting = false;

    public bool $bypassExistingPaymentAccount = false;

    public function initializeStripePaymentSetup(): void
    {
        $this->provider = 'stripe';
    }

    public function createPaymentAccount(): void
    {
        $this->is_connecting = true;

        try {
            $workspace = $this->getCurrentWorkspace();

            if (! $workspace) {
                throw new \Exception('No workspace found.');
            }

            $paymentService = app(PaymentService::class);
            $config = $this->storedPaymentConfiguration;
            $onboardingUrl = null;

            if ($config && $config->provider_account_id) {
                $onboardingUrl = $paymentService->getOnboardingUrl($config);
            } else {
                $response = $paymentService->createConnectAccount($workspace, $this->provider, []);
                $onboardingUrl = $response['onboarding_url'] ?? null;
            }

            if ($onboardingUrl) {
                $this->redirect($onboardingUrl, true);

                return;
            }

            session()->flash('status', 'Stripe account connected successfully.');
        } catch (\Throwable $exception) {
            Log::error('Failed to connect Stripe account', [
                'user_id' => Auth::id(),
                'provider' => $this->provider,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', $this->resolveStripeErrorMessage($exception->getMessage()));
        } finally {
            $this->is_connecting = false;
        }
    }

    #[Computed]
    public function storedPaymentConfiguration(): ?PaymentConfiguration
    {
        $workspace = $this->getCurrentWorkspace();

        if (! $workspace) {
            return null;
        }

        return $workspace->paymentConfigurations()
            ->where('provider', $this->provider)
            ->first();
    }

    #[Computed]
    public function isStripeVerified(): bool
    {
        return (bool) ($this->storedPaymentConfiguration && $this->storedPaymentConfiguration->is_verified);
    }

    #[Computed]
    public function hasExistingPaymentAccount(): bool
    {
        if ($this->bypassExistingPaymentAccount) {
            return false;
        }

        return (bool) ($this->storedPaymentConfiguration && $this->storedPaymentConfiguration->provider_account_id);
    }

    #[Computed]
    public function paymentAccountSubmitLabel(): string
    {
        return $this->hasExistingPaymentAccount ? 'Continue Stripe Setup' : 'Connect Stripe';
    }

    #[Computed]
    public function paymentAccountSubmittingLabel(): string
    {
        return $this->hasExistingPaymentAccount ? 'Opening Stripe...' : 'Connecting Stripe...';
    }

    protected function resetAccountFields(): void {}

    protected function afterPaymentAccountCreated(): void {}

    protected function getCurrentWorkspace(): ?Workspace
    {
        return currentWorkspace();
    }

    protected function resolveStripeErrorMessage(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'signed up for connect')) {
            return 'Stripe Connect is not enabled for this account yet. Visit https://dashboard.stripe.com/connect to enable Connect, then try again.';
        }

        return 'Failed to connect Stripe account: '.$message;
    }
}
