<?php

use App\Services\PaymentService;
use App\Support\CurrencySupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Creator Onboarding')] class extends Component {
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

    public function mount(): void
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

            $paymentService->createConnectAccount($workspace, $this->provider, [
                'bank_code' => $this->bank_code,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
                'payment_method' => $this->payment_method,
                'bank_type' => $this->bankTypeFilter,
                'currency' => $this->currency,
                'country_code' => $this->country_code,
            ]);

            session()->flash('status', 'Payment account created successfully!');
            $this->completeOnboarding();
        } catch (\Throwable $exception) {
            Log::error('Payment account creation failed in onboarding', [
                'user_id' => Auth::id(),
                'provider' => $this->provider,
                'country_code' => $this->country_code,
                'currency' => $this->currency,
                'payment_method' => $this->payment_method,
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'Failed to create payment account: '.$exception->getMessage());
        } finally {
            $this->is_creating_subaccount = false;
        }
    }

    public function skipPaymentSetup(): void
    {
        session()->flash('status', 'Setup completed! You can configure payment settings later.');
        session()->flash('info', 'Note: You will not receive payouts until payment setup is complete.');

        $this->completeOnboarding();
    }

    public function completeOnboarding(): void
    {
        try {
            $workspace = $this->getCurrentWorkspace();

            if ($workspace) {
                $workspace->completeOnboarding();
                $this->redirect('/dashboard');
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to complete onboarding', [
                'user_id' => Auth::id(),
                'error' => $exception->getMessage(),
            ]);

            session()->flash('error', 'Failed to complete onboarding: '.$exception->getMessage());
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
        $workspace = $this->getCurrentWorkspace();

        if (! $workspace) {
            return false;
        }

        $config = $workspace->paymentConfigurations()
            ->where('provider', $this->provider)
            ->first();

        return (bool) ($config && $config->provider_account_id);
    }

    private function syncPaymentMethod(): void
    {
        $methodKeys = array_keys($this->supportedPaymentMethods);

        if (! in_array($this->payment_method, $methodKeys, true)) {
            $this->payment_method = $methodKeys[0] ?? 'bank';
        }
    }

    private function defaultCurrencyForCountry(): string
    {
        $countryDefault = (string) data_get($this->selectedCountry, 'default_currency_code', '');

        if ($countryDefault && isset($this->supportedCurrencies[$countryDefault])) {
            return $countryDefault;
        }

        return array_key_first($this->supportedCurrencies) ?? 'KES';
    }

    private function resetAccountFields(): void
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

    private function getCurrentWorkspace()
    {
        return currentWorkspace();
    }
}; ?>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-xs">
    <div class="max-h-[90vh] w-full max-w-lg overflow-hidden rounded-lg bg-gray-100 shadow-xl dark:bg-zinc-950">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Welcome!</flux:heading>
                    <flux:text size="sm" class="text-accent-100">Set up your creator workspace</flux:text>
                </div>
                <div class="rounded bg-white/20 px-2 py-1 text-xs">
                    {{ ucfirst($this->provider) }}
                </div>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-6">
            @if($this->provider === 'paystack')
                @include('livewire.onboarding.providers.paystack')
            @else
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                    <flux:text class="text-amber-700">Selected provider is not supported in onboarding yet.</flux:text>
                </div>
            @endif
        </div>

        @if (session('error'))
            <div class="mx-6 mb-6 rounded-md border border-red-200 bg-red-50 p-3">
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        @endif

        @if (session('status'))
            <div class="mx-6 mb-6 rounded-md border border-primary-200 bg-primary-50 p-3">
                <flux:text class="text-primary-800">{{ session('status') }}</flux:text>
            </div>
        @endif

        @if (session('info'))
            <div class="mx-6 mb-6 rounded-md border border-amber-200 bg-amber-50 p-3">
                <flux:text class="text-amber-700">{{ session('info') }}</flux:text>
            </div>
        @endif
    </div>
</div>
