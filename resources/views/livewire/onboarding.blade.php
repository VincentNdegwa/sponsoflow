<?php

use App\Services\PaymentService;
use App\Support\CurrencySupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Creator Onboarding')] class extends Component {
    public string $country_code = 'US';
    public string $currency = 'USD';
    
    public string $bank_code = '';
    public string $account_number = '';
    public string $account_name = '';
    public array $supported_banks = [];
    public bool $is_verifying = false;
    public bool $is_creating_subaccount = false;
    public string $verification_status = '';
    
    public int $current_step = 1;
    public bool $is_saving = false;

    public function mount(): void
    {
        $workspace = $this->getCurrentWorkspace();
        
        if ($workspace) {
            $this->country_code = $workspace->country_code ?: 'US';
            $this->currency = $workspace->currency ?: 'USD';
        }
    }

    public function saveCountryAndCurrency(): void
    {
        $this->validate([
            'country_code' => 'required|string|size:2',
            'currency' => 'required|string|size:3',
        ]);

        try {
            $workspace = $this->getCurrentWorkspace();
            
            if (!$workspace) {
                throw new \Exception('No workspace found.');
            }

            $recommendedProvider = CurrencySupport::getRecommendedProvider($this->country_code);
            if (!CurrencySupport::isCurrencySupportedByProvider($this->currency, $recommendedProvider)) {
                throw new \Exception("Currency {$this->currency} is not supported by the recommended provider ({$recommendedProvider}) for {$this->country_code}");
            }

            $workspace->update([
                'country_code' => $this->country_code,
                'currency' => $this->currency,
            ]);
            
            if ($this->requiresPaymentSetup()) {
                $this->loadSupportedBanks();
                $this->current_step = 2;
            } else {
                $this->completeOnboarding();
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save settings: ' . $e->getMessage());
        }
    }

    public function loadSupportedBanks(): void
    {
        try {
            $workspace = $this->getCurrentWorkspace();
            if (!$workspace) {
                $this->supported_banks = [];
                return;
            }
            
            $provider = $workspace->getRecommendedProvider();
            
            if ($provider !== 'paystack') {
                $this->supported_banks = [];
                return;
            }
            
            $paymentService = app(PaymentService::class);
            $banks = $paymentService->getSupportedBanks($provider, $workspace->country_code);
            
            // Remove duplicates by bank code
            $uniqueBanks = [];
            $seenCodes = [];
            
            foreach ($banks as $bank) {
                if (!in_array($bank['code'], $seenCodes)) {
                    $uniqueBanks[] = $bank;
                    $seenCodes[] = $bank['code'];
                }
            }
            
            $this->supported_banks = $uniqueBanks;
        } catch (\Exception $e) {
            $this->supported_banks = [];
        }
    }

    public function verifyAccount(): void
    {
        $this->validate([
            'bank_code' => 'required|string',
            'account_number' => 'required|string|min:8',
        ]);

        $this->is_verifying = true;
        $this->verification_status = '';

        try {
            $workspace = $this->getCurrentWorkspace();
            if (!$workspace) {
                throw new \Exception('No workspace found.');
            }
            
            $provider = $workspace->getRecommendedProvider();
            $paymentService = app(PaymentService::class);
            $verification = $paymentService->verifyBankAccount($this->account_number, $this->bank_code, $provider);
            
            $this->account_name = $verification['account_name'];
            $this->verification_status = 'verified';
            
        } catch (\Exception $e) {
            $this->verification_status = 'failed';
            $this->account_name = '';
            session()->flash('error', 'Verification failed: ' . $e->getMessage());
        } finally {
            $this->is_verifying = false;
        }
    }

    public function createPaymentAccount(): void
    {
        if (!$this->account_name || $this->verification_status !== 'verified') {
            session()->flash('error', 'Please verify your account first.');
            return;
        }

        $this->is_creating_subaccount = true;

        try {
            $workspace = $this->getCurrentWorkspace();
            
            if (!$workspace) {
                throw new \Exception('No workspace found.');
            }

            $provider = $workspace->getRecommendedProvider();
            $paymentService = app(PaymentService::class);
            
            $bankDetails = [
                'bank_code' => $this->bank_code,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
            ];
            
            $result = $paymentService->createConnectAccount($workspace, $provider, $bankDetails);
            
            session()->flash('status', 'Payment account created successfully!');
            $this->completeOnboarding();
            
        } catch (\Exception $e) {
            Log::error('Payment account creation failed in onboarding', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to create payment account: ' . $e->getMessage());
        } finally {
            $this->is_creating_subaccount = false;
        }
    }

    public function skipPaymentSetup(): void
    {
        session()->flash('status', 'Setup completed! You can configure payment settings later.');
        session()->flash('info', 'Note: You won\'t be able to receive payments until you set up your payment account in Settings > Payment.');
        
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
        } catch (\Exception $e) {
            Log::error('Failed to complete onboarding', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to complete onboarding: ' . $e->getMessage());
        }
    }

    public function previousStep(): void
    {
        if ($this->current_step > 1) {
            $this->current_step--;
        }
    }

    #[Computed]
    public function supportedCountries(): array
    {
        return CurrencySupport::getSupportedCountries();
    }

    #[Computed]
    public function supportedCurrencies(): array
    {
        return CurrencySupport::getSupportedCurrencies();
    }

    #[Computed]
    public function recommendedProvider(): string
    {
        return CurrencySupport::getRecommendedProvider($this->country_code);
    }



    #[Computed]
    public function hasExistingPaymentAccount(): bool
    {
        $workspace = $this->getCurrentWorkspace();
        if (!$workspace) {
            return false;
        }
        
        $provider = $workspace->getRecommendedProvider();
        $config = $workspace->paymentConfigurations()
            ->where('provider', $provider)
            ->first();
            
        return $config && $config->provider_account_id;
    }

    private function getCurrentWorkspace()
    {
        return currentWorkspace();
    }

    private function requiresPaymentSetup(): bool
    {
        $provider = $this->recommendedProvider;
        return $provider === 'paystack';
    }

    public function updatedCountryCode(): void
    {
        $defaultCurrency = CurrencySupport::getDefaultCurrency($this->country_code);
        $this->currency = $defaultCurrency;
    }

    public function getBankName(string $code): string
    {
        $bank = collect($this->supported_banks)->firstWhere('code', $code);
        return $bank['name'] ?? 'Unknown Bank';
    }
}; ?>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 backdrop-blur-xs">
    <div class="rounded-lg shadow-xl w-full bg-gray-100 dark:bg-zinc-950 max-w-lg mx-4 max-h-[90vh] overflow-hidden">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg" >Welcome!</flux:heading>
                    <flux:text size="sm" class="text-accent-100">
                        Set up your creator workspace
                    </flux:text>
                </div>
                <div class="text-xs bg-white/20 px-2 py-1 rounded">
                    Step {{ $current_step }} of {{ $this->requiresPaymentSetup() ? 2 : 1 }}
                </div>
            </div>
        </div>

        @if($this->requiresPaymentSetup())
            <div class="h-1">
                <div class="bg-accent h-1 transition-all duration-300" 
                     style="width: {{ ($current_step / 2) * 100 }}%"></div>
            </div>
        @endif
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            @if($current_step === 1)
                <div class="space-y-6">
                    <div class="text-center mb-6">
                        <flux:icon.globe-americas class="w-12 h-12 text-accent-600 mx-auto mb-2" />
                        <flux:heading size="lg">Choose Your Country & Currency</flux:heading>
                    </div>

                    <form wire:submit="saveCountryAndCurrency" class="space-y-4">
                        <div>
                            <flux:select wire:model.live="country_code" label="Country" placeholder="Select your country" required>
                                @foreach($this->supportedCountries as $code => $country)
                                    <flux:select.option value="{{ $code }}">{{ $country['name'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div>
                            <flux:select wire:model="currency" label="Currency" placeholder="Select currency" required>
                                @foreach($this->supportedCurrencies as $code => $currencyData)
                                    <flux:select.option value="{{ $code }}">
                                        {{ $currencyData['symbol'] }} {{ $code }} - {{ $currencyData['name'] }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        @if($this->country_code && $this->currency)
                            <div class="p-3 bg-accent-50 border border-accent-200 rounded-md">
                                <div class="flex items-center gap-2">
                                    <flux:icon.information-circle class="w-5 h-5 text-accent-600" />
                                    <div>
                                        <flux:text class="font-medium text-accent-800">
                                            Provider: {{ ucfirst($this->recommendedProvider) }}
                                        </flux:text>
                                        <flux:text size="sm" class="text-accent-600">
                                            Currency: {{ $this->supportedCurrencies[$this->currency]['symbol'] }}{{ $this->currency }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex justify-end pt-4">
                            <flux:button type="submit" variant="primary">
                                @if($this->requiresPaymentSetup())
                                    Next
                                @else
                                    Complete Setup
                                @endif
                            </flux:button>
                        </div>
                    </form>
                </div>

            @elseif($current_step === 2 && $this->requiresPaymentSetup())
                <div class="space-y-6">
                    <div class="text-center mb-6">
                        <flux:icon.credit-card class="w-12 h-12 text-primary-600 mx-auto mb-2" />
                        <flux:heading size="lg">Set Up Payment Account</flux:heading>
                    </div>

                    @if($this->hasExistingPaymentAccount)
                        <div class="p-3 bg-primary-50 border border-primary-200 rounded-md">
                            <div class="flex items-center gap-2">
                                <flux:icon.check-circle class="w-5 h-5 text-primary-600" />
                                <flux:text class="font-medium text-primary-800">Payment account already configured</flux:text>
                            </div>
                        </div>
                    @else
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-md">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:text class="font-medium text-amber-800">Optional - You can skip this step</flux:text>
                                    <flux:text size="sm" class="text-amber-600">You can configure payments later in settings</flux:text>
                                    <flux:text size="xs" class="text-amber-500 mt-1">⚠️ You won't be able to receive payments until payment setup is complete</flux:text>
                                </div>
                                <flux:button wire:click="skipPaymentSetup" variant="outline" size="sm">
                                    Skip
                                </flux:button>
                            </div>
                        </div>
                    @endif

                    @unless($this->hasExistingPaymentAccount)
                        <div class="space-y-4">
                            <div>
                                <flux:select wire:model.live="bank_code" label="Select Bank" placeholder="Choose your bank">
                                    @foreach($supported_banks as $bank)
                                        <flux:select.option value="{{ $bank['code'] }}">{{ $bank['name'] }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                        <div>
                            <flux:input 
                                wire:model.live="account_number" 
                                label="Account Number" 
                                type="text" 
                                placeholder="Enter account number"
                            />
                        </div>

                        @if($bank_code && $account_number)
                            <div class="flex items-center gap-4">
                                <flux:button 
                                    type="button" 
                                    wire:click="verifyAccount" 
                                    variant="outline"
                                    :disabled="$is_verifying"
                                >
                                    @if($is_verifying)
                                        <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                                        Verifying...
                                    @else
                                        Verify Account
                                    @endif
                                </flux:button>

                                @if($verification_status === 'verified')
                                    <div class="flex items-center text-primary-600">
                                        <flux:icon.check-circle class="w-5 h-5" />
                                        <flux:text size="sm" class="ml-2 font-medium">
                                            {{ $account_name }}
                                        </flux:text>
                                    </div>
                                @elseif($verification_status === 'failed')
                                    <div class="flex items-center text-red-600">
                                        <flux:icon.x-circle class="w-5 h-5" />
                                        <flux:text size="sm" class="ml-2">
                                            Verification failed
                                        </flux:text>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if($account_name && $verification_status === 'verified')
                            <div class="p-4 bg-primary-50 border border-primary-200 rounded-md">
                                <flux:text class="font-medium text-primary-800">
                                    Account Name: {{ $account_name }}
                                </flux:text>
                                <flux:text size="sm" class="text-primary-600 mt-1">
                                    Bank: {{ $this->getBankName($bank_code) }}
                                </flux:text>
                            </div>

                            <div class="flex justify-end">
                                <flux:button 
                                    wire:click="createPaymentAccount" 
                                    variant="primary"
                                    :disabled="$is_creating_subaccount"
                                    class="min-w-[150px]"
                                >
                                    @if($is_creating_subaccount)
                                        <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                                        Creating Account...
                                    @else
                                        Create Payment Account
                                    @endif
                                </flux:button>
                            </div>
                        @endif
                        </div>
                    @else
                        <div class="flex justify-end pt-4">
                            <flux:button wire:click="completeOnboarding" variant="primary">
                                Continue to Dashboard
                            </flux:button>
                        </div>
                    @endunless

                    <div class="flex justify-between pt-4">
                        <flux:button wire:click="previousStep" variant="ghost">
                            ← Back
                        </flux:button>
                    </div>
                </div>
            @endif
        </div>

        @if (session('error'))
            <div class="mx-6 mb-6 p-3 bg-red-50 border border-red-200 rounded-md">
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        @endif
        
        @if (session('status'))
            <div class="mx-6 mb-6 p-3 bg-primary-50 border border-primary-200 rounded-md">
                <flux:text class="text-primary-800">{{ session('status') }}</flux:text>
            </div>
        @endif
        
        @if (session('info'))
            <div class="mx-6 mb-6 p-3 bg-amber-50 border border-amber-200 rounded-md">
                <flux:text class="text-amber-700">{{ session('info') }}</flux:text>
            </div>
        @endif
    </div>
</div>