<div class="space-y-6">
    <div class="text-center">
        <flux:icon.globe-americas class="mx-auto mb-2 h-12 w-12 text-accent-600" />
        <flux:heading size="lg">Set Up Paystack Payouts</flux:heading>
    </div>

    <div class="space-y-4">
        <flux:select wire:model.change.live="country_code" label="Country" placeholder="Select your country" required>
            @foreach($this->supportedCountries as $code => $country)
                <flux:select.option value="{{ $code }}">{{ $country['name'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.change.live="currency" label="Currency" placeholder="Select currency" required>
            @foreach($this->supportedCurrencies as $code => $currencyData)
                <flux:select.option value="{{ $code }}">
                    {{ $currencyData['symbol'] }} {{ $code }} - {{ $currencyData['name'] }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.change.live="payment_method" label="Payment Method" placeholder="Select payment method" required>
            @foreach($this->supportedPaymentMethods as $method => $config)
                <flux:select.option value="{{ $method }}">{{ ucfirst(str_replace('_', ' ', $method)) }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="rounded-md border border-accent-200 bg-accent-50 p-3">
            <div class="flex items-center gap-2">
                <flux:icon.information-circle class="h-5 w-5 text-accent-600" />
                <div>
                    <flux:text class="font-medium text-accent-800">
                        Method type: {{ $this->bankTypeFilter ?? 'N/A' }}
                    </flux:text>
                    <flux:text size="sm" class="text-accent-700">
                        Verification required: {{ $this->requiresBankVerification ? 'Yes' : 'No' }}
                    </flux:text>
                </div>
            </div>
        </div>
    </div>

    @if($this->hasExistingPaymentAccount)
        <div class="rounded-md border border-primary-200 bg-primary-50 p-3">
            <div class="flex items-center gap-2">
                <flux:icon.check-circle class="h-5 w-5 text-primary-600" />
                <flux:text class="font-medium text-primary-800">Payment account already configured</flux:text>
            </div>
            <div class="mt-3 flex justify-end">
                <flux:button wire:click="completeOnboarding" variant="primary">Continue to Dashboard</flux:button>
            </div>
        </div>
    @else
        <div class="space-y-4">
            <flux:select wire:model="bank_code" label="Bank / Channel" placeholder="Choose bank or channel" required>
                @foreach($supported_banks as $bank)
                    <flux:select.option value="{{ $bank['code'] }}">{{ $bank['name'] }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="account_number"
                label="Account Number"
                type="text"
                placeholder="{{ $this->accountInputPlaceholder }}"
            />

            @if($this->requiresManualAccountName)
                <flux:input
                    wire:model="account_name"
                    label="Account Name"
                    type="text"
                    placeholder="Enter account name"
                />
            @endif

            <div class="flex items-center gap-4">
                @if($this->requiresBankVerification)
                    <flux:button
                        type="button"
                        wire:click="verifyAccount"
                        variant="outline"
                        :disabled="$is_verifying"
                    >
                        @if($is_verifying)
                            <flux:icon.arrow-path class="h-4 w-4 animate-spin" />
                            Verifying...
                        @else
                            Verify Account
                        @endif
                    </flux:button>
                @endif

                @if($verification_status === 'verified' || $verification_status === 'skipped')
                    <div class="flex items-center text-primary-600">
                        <flux:icon.check-circle class="h-5 w-5" />
                        <flux:text size="sm" class="ml-2 font-medium">
                            {{ $account_name ?: 'Ready to continue' }}
                        </flux:text>
                    </div>
                @elseif($verification_status === 'failed')
                    <div class="flex items-center text-red-600">
                        <flux:icon.x-circle class="h-5 w-5" />
                        <flux:text size="sm" class="ml-2">Verification failed</flux:text>
                    </div>
                @endif
            </div>

            @if($account_name && ($verification_status === 'verified' || $verification_status === 'skipped'))
                <div class="rounded-md border border-primary-200 bg-primary-50 p-4">
                    <flux:text class="font-medium text-primary-800">Account Name: {{ $account_name }}</flux:text>
                    @if($this->requiresBankVerification)
                        <flux:text size="sm" class="mt-1 text-primary-600">Bank: {{ $this->getBankName($bank_code) }}</flux:text>
                    @endif
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button
                    wire:click="createPaymentAccount"
                    variant="primary"
                    :disabled="$is_creating_subaccount"
                    class="min-w-37.5"
                >
                    @if($is_creating_subaccount)
                        <flux:icon.arrow-path class="h-4 w-4 animate-spin" />
                        Creating Account...
                    @else
                        Create Payment Account
                    @endif
                </flux:button>
            </div>
        </div>
    @endif
</div>
