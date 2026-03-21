<?php

namespace App\Livewire\Concerns;

use App\Services\PaymentService;
use App\Support\CurrencySupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

trait HandlesPaystackPaymentSetup
{
    public string $provider = CurrencySupport::PAYSTACK_PROVIDER;

    public string $country_code = 'KE';

    public string $currency = 'KES';

    public string $payment_method = 'bank';

    public string $bank_code = '';

    public string $account_number = '';

    public string $account_name = '';

    public array $supported_banks = [];

    public array $provider_countries = [];

    public bool $is_verifying = false;

    public bool $is_creating_subaccount = false;

    public string $verification_status = '';

    public bool $bypassExistingPaymentAccount = false;

    public function initializePaystackPaymentSetup(): void
    {
        $workspace = $this->getCurrentWorkspace();

        $this->refreshProviderOptions();

        $supportedCountries = $this->supportedCountries;
        $defaultCountryCode = isset($supportedCountries['KE']) ? 'KE' : (array_key_first($supportedCountries) ?? 'KE');

        if ($workspace && $workspace->country_code && isset($supportedCountries[$workspace->country_code])) {
            $this->country_code = $workspace->country_code;
        } else {
            $this->country_code = $defaultCountryCode;
        }

        $supportedCurrencyCodes = array_keys($this->supportedCurrencies);

        if ($workspace && $workspace->currency && in_array($workspace->currency, $supportedCurrencyCodes, true)) {
            $this->currency = $workspace->currency;
        } else {
            $this->currency = $this->defaultCurrencyForCountry();
        }

        $this->syncPaymentMethod();
        $this->loadSupportedBanks();
    }

    public function refreshProviderOptions(): void
    {
        try {
            $paymentService = app(PaymentService::class);
            $countries = $paymentService->getSupportedCountries($this->provider);

            $this->provider_countries = collect($countries)
                ->filter(fn (array $country): bool => ! empty($country['iso_code']) && ! empty($country['name']))
                ->filter(fn (array $country): bool => (bool) ($country['active_for_dashboard_onboarding'] ?? true))
                ->keyBy(fn (array $country): string => strtoupper($country['iso_code']))
                ->all();
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch provider countries in onboarding', [
                'provider' => $this->provider,
                'error' => $exception->getMessage(),
            ]);

            $this->provider_countries = [];
        }
    }

    public function saveCountryAndCurrency(): void
    {
        $supportedCountryCodes = array_keys($this->supportedCountries);
        $supportedCurrencyCodes = array_keys($this->supportedCurrencies);
        $supportedPaymentMethods = array_keys($this->supportedPaymentMethods);

        $this->validate([
            'country_code' => ['required', 'string', 'size:2', Rule::in($supportedCountryCodes)],
            'currency' => ['required', 'string', 'size:3', Rule::in($supportedCurrencyCodes)],
            'payment_method' => ['required', 'string', Rule::in($supportedPaymentMethods)],
        ]);

        try {
            $workspace = $this->getCurrentWorkspace();

            if (! $workspace) {
                throw new \Exception('No workspace found.');
            }

            $workspace->update([
                'country_code' => $this->country_code,
                'currency' => $this->currency,
            ]);

            session()->flash('status', 'Country and currency saved. Continue with account setup.');
        } catch (\Throwable $exception) {
            session()->flash('error', 'Failed to save settings: '.$exception->getMessage());
        }
    }

    public function loadSupportedBanks(): void
    {
        try {
            $paymentService = app(PaymentService::class);
            $banks = $paymentService->getSupportedBanks(
                $this->provider,
                $this->country_code,
                $this->currency,
                $this->bankTypeFilter
            );

            $this->supported_banks = collect($banks)
                ->filter(fn (array $bank): bool => ! empty($bank['code']) && ! empty($bank['name']))
                ->unique('code')
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::error('Failed to load supported banks in onboarding', [
                'provider' => $this->provider,
                'country_code' => $this->country_code,
                'currency' => $this->currency,
                'payment_method' => $this->payment_method,
                'error' => $exception->getMessage(),
            ]);

            $this->supported_banks = [];
        }
    }

    public function verifyAccount(): void
    {
        if (! $this->requiresBankVerification) {
            $this->verification_status = 'skipped';

            if (! $this->account_name) {
                $this->account_name = Auth::user()?->name ?? '';
            }

            session()->flash('status', 'Verification is not required for the selected payment method.');

            return;
        }

        $this->validate([
            'bank_code' => ['required', 'string'],
            'account_number' => ['required', 'string', 'min:6'],
        ]);

        $this->is_verifying = true;
        $this->verification_status = '';

        try {
            $paymentService = app(PaymentService::class);
            $verification = $paymentService->verifyBankAccount($this->account_number, $this->bank_code, $this->provider);

            $this->account_name = (string) ($verification['account_name'] ?? '');
            $this->verification_status = 'verified';
        } catch (\Throwable $exception) {
            $this->verification_status = 'failed';
            $this->account_name = '';
            session()->flash('error', 'Verification failed: '.$exception->getMessage());
        } finally {
            $this->is_verifying = false;
        }
    }

    public function createPaymentAccount(): void
    {
        $this->validate([
            'bank_code' => ['required', 'string'],
            'account_number' => ['required', 'string', 'min:6'],
        ]);

        if ($this->requiresBankVerification && $this->verification_status !== 'verified') {
            session()->flash('error', 'Please verify account details before proceeding.');

            return;
        }

        if (! $this->account_name) {
            session()->flash('error', 'Account name is required.');

            return;
        }

        $this->is_creating_subaccount = true;

        try {
            $workspace = $this->getCurrentWorkspace();

            if (! $workspace) {
                throw new \Exception('No workspace found.');
            }

            $workspace->update([
                'country_code' => $this->country_code,
                'currency' => $this->currency,
            ]);

            $paymentService = app(PaymentService::class);
            $bankDetails = [
                'bank_code' => $this->bank_code,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
                'payment_method' => $this->payment_method,
                'bank_type' => $this->bankTypeFilter,
                'currency' => $this->currency,
                'country_code' => $this->country_code,
            ];

            $isUpdateAction = $this->hasPersistedPaymentAccount;

            if ($isUpdateAction) {
                $paymentService->updateConnectAccount($workspace, $this->provider, $bankDetails);
            } else {
                $paymentService->createConnectAccount($workspace, $this->provider, $bankDetails);
            }

            session()->flash('status', $isUpdateAction
                ? 'Payment account updated successfully!'
                : 'Payment account created successfully!');
            $this->afterPaymentAccountCreated();
        } catch (\Throwable $exception) {
            Log::error('Payment account creation failed in onboarding', [
                'user_id' => Auth::id(),
                'provider' => $this->provider,
                'country_code' => $this->country_code,
                'currency' => $this->currency,
                'payment_method' => $this->payment_method,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'Failed to save payment account: '.$exception->getMessage());
        } finally {
            $this->is_creating_subaccount = false;
        }
    }

    public function updatedCountryCode(): void
    {
        $this->currency = $this->defaultCurrencyForCountry();
        $this->syncPaymentMethod();
        $this->resetAccountFields();
        $this->loadSupportedBanks();
    }

    public function updatedCurrency(): void
    {
        $this->syncPaymentMethod();
        $this->resetAccountFields();
        $this->loadSupportedBanks();
    }

    public function updatedPaymentMethod(): void
    {
        $this->resetAccountFields();
        $this->loadSupportedBanks();
    }

    #[Computed]
    public function supportedCountries(): array
    {
        return $this->provider_countries;
    }

    #[Computed]
    public function supportedCurrencies(): array
    {
        $supportedCurrencyCodes = data_get($this->selectedCountry, 'relationships.currency.data', []);
        $methodConfigsByCurrency = data_get($this->selectedCountry, 'relationships.currency.supported_currencies', []);
        $knownCurrencies = CurrencySupport::getSupportedCurrencies();

        return collect($supportedCurrencyCodes)
            ->filter(fn (mixed $code): bool => is_string($code) && $code !== '')
            ->map(fn (string $code): string => strtoupper($code))
            ->unique()
            ->mapWithKeys(function (string $code) use ($knownCurrencies, $methodConfigsByCurrency): array {
                $upper = strtoupper($code);
                $known = $knownCurrencies[$upper] ?? null;
                $details = data_get($methodConfigsByCurrency, $upper, data_get($methodConfigsByCurrency, strtolower($upper), []));

                return [
                    $upper => [
                        'name' => $known['name'] ?? $upper,
                        'symbol' => $known['symbol'] ?? $upper,
                        'details' => $details,
                    ],
                ];
            })
            ->all();
    }

    #[Computed]
    public function supportedPaymentMethods(): array
    {
        $methodsByCurrency = data_get($this->selectedCountry, 'relationships.currency.supported_currencies', []);
        $methods = data_get($methodsByCurrency, $this->currency, data_get($methodsByCurrency, strtolower($this->currency), []));

        return collect($methods)
            ->filter(fn (mixed $value): bool => is_array($value))
            ->mapWithKeys(fn (array $value, string $key): array => [$key => $value])
            ->all();
    }

    #[Computed]
    public function selectedCountry(): array
    {
        return $this->supportedCountries[$this->country_code] ?? [];
    }

    #[Computed]
    public function selectedPaymentMethodConfig(): array
    {
        return $this->supportedPaymentMethods[$this->payment_method] ?? [];
    }

    #[Computed]
    public function bankTypeFilter(): ?string
    {
        $bankType = data_get($this->selectedPaymentMethodConfig, 'bank_type');

        return is_string($bankType) ? $bankType : null;
    }

    #[Computed]
    public function accountInputLabel(): string
    {
        $methodConfig = $this->selectedPaymentMethodConfig;

        return (string) ($methodConfig['account_number_label']
            ?? $methodConfig['phone_number_label']
            ?? 'Account Number');
    }

    #[Computed]
    public function accountInputPlaceholder(): string
    {
        return (string) data_get($this->selectedPaymentMethodConfig, 'placeholder', 'Enter account number');
    }

    #[Computed]
    public function requiresBankVerification(): bool
    {
        return (bool) data_get($this->selectedPaymentMethodConfig, 'account_verification_required', false);
    }

    #[Computed]
    public function requiresManualAccountName(): bool
    {
        return ! $this->requiresBankVerification || (bool) data_get($this->selectedPaymentMethodConfig, 'account_name', false);
    }

    #[Computed]
    public function hasExistingPaymentAccount(): bool
    {
        if ($this->bypassExistingPaymentAccount) {
            return false;
        }

        return $this->hasPersistedPaymentAccount;
    }

    #[Computed]
    public function hasPersistedPaymentAccount(): bool
    {
        $workspace = $this->getCurrentWorkspace();

        if (! $workspace) {
            return false;
        }

        $config = $workspace->paymentConfigurations()
            ->where('provider', $this->provider)
            ->first();

        return (bool) ($config && $config->provider_account_id);
    }

    #[Computed]
    public function paymentAccountSubmitLabel(): string
    {
        return $this->hasPersistedPaymentAccount ? 'Update Payment Account' : 'Create Payment Account';
    }

    #[Computed]
    public function paymentAccountSubmittingLabel(): string
    {
        return $this->hasPersistedPaymentAccount ? 'Updating Account...' : 'Creating Account...';
    }

    protected function syncPaymentMethod(): void
    {
        $methodKeys = array_keys($this->supportedPaymentMethods);

        if (! in_array($this->payment_method, $methodKeys, true)) {
            $this->payment_method = $methodKeys[0] ?? 'bank';
        }
    }

    protected function defaultCurrencyForCountry(): string
    {
        $countryDefault = (string) data_get($this->selectedCountry, 'default_currency_code', '');

        if ($countryDefault && isset($this->supportedCurrencies[$countryDefault])) {
            return $countryDefault;
        }

        return array_key_first($this->supportedCurrencies) ?? 'KES';
    }

    protected function resetAccountFields(): void
    {
        $this->bank_code = '';
        $this->account_number = '';
        $this->account_name = '';
        $this->verification_status = '';
    }

    public function getBankName(string $bankCode): string
    {
        $bank = collect($this->supported_banks)->firstWhere('code', $bankCode);

        return is_array($bank) && isset($bank['name'])
            ? (string) $bank['name']
            : $bankCode;
    }

    protected function afterPaymentAccountCreated(): void
    {
        // Component-specific hooks can override this.
    }

    protected function getCurrentWorkspace()
    {
        return currentWorkspace();
    }
}
