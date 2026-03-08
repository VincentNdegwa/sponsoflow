<?php

use App\Services\PaymentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Payment settings')] class extends Component {
    public bool $showForm = false;
    public string $bank_code = '';
    public string $account_number = '';
    public string $account_name = '';
    public array $supported_banks = [];
    public bool $is_verifying = false;
    public bool $is_creating_subaccount = false;
    public string $verification_status = '';

    public function mount(): void
    {
        $this->loadSupportedBanks();
        $this->showForm = !$this->hasPaystackAccount;
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
            $countryCode = $workspace->country_code;
            $paymentService = app(PaymentService::class);
            $banks = $paymentService->getSupportedBanks($provider, $countryCode);
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
            Log::error('Failed to load banks', ['error' => $e->getMessage()]);
            $this->supported_banks = [];
        }
    }

    public function verifyAccount(): void
    {
        $this->validate([
            'bank_code' => 'required|string',
            'account_number' => 'required|string',
        ]);

        $this->is_verifying = true;
        $this->verification_status = '';

        try {
            $workspace = $this->getCurrentWorkspace();
            if (!$workspace) {
                throw new \Exception('No workspace found. Please create a workspace first.');
            }
            
            $provider = $workspace->getRecommendedProvider();
            $paymentService = app(PaymentService::class);
            $verification = $paymentService->verifyBankAccount($this->account_number, $this->bank_code, $provider);
            
            $this->account_name = $verification['account_name'];
            $this->verification_status = 'verified';
            
            session()->flash('status', 'Account verified successfully!');
        } catch (\Exception $e) {
            $this->verification_status = 'failed';
            $this->account_name = '';
            session()->flash('error', 'Verification failed: ' . $e->getMessage());
        } finally {
            $this->is_verifying = false;
        }
    }

    public function createPaystackSubaccount(): void
    {
        if (!$this->account_name || $this->verification_status !== 'verified') {
            session()->flash('error', 'Please verify your account first.');
            return;
        }

        $this->is_creating_subaccount = true;

        try {
            $user = Auth::user();
            $workspace = currentWorkspace();
            
            if (!$workspace) {
                throw new \Exception('No workspace found. Please create a workspace first.');
            }

            $paymentService = app(PaymentService::class);
            
            $bankDetails = [
                'bank_code' => $this->bank_code,
                'account_number' => $this->account_number,
                'account_name' => $this->account_name,
            ];
            
            $result = $paymentService->createConnectAccount($workspace, 'paystack', $bankDetails);
            
            session()->flash('status', 'Payment account updated successfully!');
            $this->resetForm();
            
        } catch (\Exception $e) {
            Log::error('Subaccount creation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'Failed to update payment account: ' . $e->getMessage());
        } finally {
            $this->is_creating_subaccount = false;
        }
    }

    public function resetForm(): void
    {
        $this->bank_code = '';
        $this->account_number = '';
        $this->account_name = '';
        $this->verification_status = '';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    #[Computed]
    public function paymentConfiguration()
    {
        $workspace = $this->getCurrentWorkspace();
        
        if (!$workspace) {
            return null;
        }
        
        $provider = $workspace->getRecommendedProvider();
        
        return $workspace->paymentConfigurations()
            ->where('provider', $provider)
            ->first();
    }

    #[Computed]
    public function hasPaystackAccount(): bool
    {
        return $this->paymentConfiguration && $this->paymentConfiguration->provider_account_id;
    }

    #[Computed]
    public function isPaystackVerified(): bool
    {
        return $this->paymentConfiguration && $this->paymentConfiguration->is_verified;
    }

    #[Computed]
    public function recommendedProvider(): string
    {
        $workspace = $this->getCurrentWorkspace();
        return $workspace ? $workspace->getRecommendedProvider() : 'stripe';
    }

    #[Computed]
    public function workspaceCurrency(): string
    {
        $workspace = $this->getCurrentWorkspace();
        return $workspace ? $workspace->currency : 'USD';
    }

    #[Computed]
    public function workspaceCountry(): string
    {
        $workspace = $this->getCurrentWorkspace();
        return $workspace ? $workspace->country_code : 'US';
    }

    private function getCurrentWorkspace()
    {
        return currentWorkspace();
    }

    public function getBankName(string $code): string
    {
        $bank = collect($this->supported_banks)->firstWhere('code', $code);
        return $bank['name'] ?? 'Unknown Bank';
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Payment settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Payment Account')" :subheading="__('Configure your payment account to receive funds')">
        
        @if($this->hasPaystackAccount && !$this->showForm)
            <div class="space-y-6">
                <flux:card class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:heading size="lg">{{ ucfirst($this->recommendedProvider) }} Account</flux:heading>
                            <flux:subheading class="mt-1">
                                Status: 
                                @if($this->isPaystackVerified)
                                    <flux:badge color="green" size="sm">Verified & Active</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Pending Verification</flux:badge>
                                @endif
                            </flux:subheading>
                        </div>
                        <div class="text-right">
                            <flux:text size="sm" class="text-gray-500">
                                Currency: {{ $this->workspaceCurrency }}
                            </flux:text>
                            <flux:text size="sm" class="text-gray-500">
                                Account ID: {{ $this->paymentConfiguration->provider_account_id }}
                            </flux:text>
                        </div>
                    </div>

                    @if($this->isPaystackVerified)
                        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex">
                                <flux:icon.check-circle class="w-5 h-5 text-green-400 mt-0.5" />
                                <div class="ml-3">
                                    <flux:text class="text-green-800 font-medium">
                                        Your payment account is active and ready to receive funds!
                                    </flux:text>
                                    <flux:text size="sm" class="text-green-600 mt-1">
                                        You can now receive payments for your sponsorship slots.
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    @endif
                </flux:card>

                <flux:button wire:click="$set('showForm', true)" variant="filled" class="w-full">
                    {{ __('Change Payment Account') }}
                </flux:button>
            </div>
        @else
            {{-- Payment Setup Form --}}
            <div class="space-y-6">

                <form wire:submit="createPaystackSubaccount" class="space-y-6">
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
                            required 
                        />
                        <flux:text size="sm" class="text-gray-500 mt-1">
                            Enter your bank account number
                        </flux:text>
                    </div>

                    {{-- Verify Button --}}
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
                                <div class="flex items-center text-green-600">
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

                    {{-- Account Name Display --}}
                    @if($account_name && $verification_status === 'verified')
                        <div class="p-4 bg-green-50 border border-green-200 rounded-md">
                            <flux:text class="font-medium text-green-800">
                                Account Name: {{ $account_name }}
                            </flux:text>
                            <flux:text size="sm" class="text-green-600 mt-1">
                                Bank: {{ $this->getBankName($bank_code) }}
                            </flux:text>
                        </div>

                        {{-- Create Account Button --}}
                        <div class="flex justify-between gap-2">
                            <flux:button 
                                type="button"
                                wire:click="cancelForm"
                                variant="ghost"
                                :disabled="$is_creating_subaccount"
                            >
                                Cancel
                            </flux:button>
                            <flux:button 
                                type="submit" 
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
                </form>

                {{-- Security Notice --}}
                <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
                    <div class="flex">
                        <flux:icon.shield-check class="w-5 h-5 text-gray-400 mt-0.5" />
                        <div class="ml-3">
                            <flux:text class="text-gray-800 font-medium text-sm">
                                Your security is important to us
                            </flux:text>
                            <flux:text size="sm" class="text-gray-600 mt-1">
                                Your bank details are only used for verification with {{ ucfirst($this->recommendedProvider) }} and are not stored on our servers. We only save the secure account reference provided by {{ ucfirst($this->recommendedProvider) }}.
                            </flux:text>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                <flux:text class="text-green-800">{{ session('status') }}</flux:text>
            </div>
        @endif

        @if (session('error'))
            <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                <flux:text class="text-red-800">{{ session('error') }}</flux:text>
            </div>
        @endif
    </x-pages::settings.layout>
</section>