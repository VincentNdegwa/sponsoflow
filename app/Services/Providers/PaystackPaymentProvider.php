<?php

namespace App\Services\Providers;

use App\Enums\BookingStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\ExchangeRate;
use App\Models\PaymentConfiguration;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\PaymentReceivedNotification;
use App\Services\BookingShadowCampaignService;
use App\Services\ExchangeRateService;
use App\Services\GuestAccountCreationService;
use App\Services\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaystackPaymentProvider implements PaymentProviderInterface
{
    protected string $baseUrl;

    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api.paystack.co';
        $this->secretKey = (string) config('services.paystack.secret_key');

        if ($this->secretKey === '') {
            throw new \Exception('Paystack secret key not configured');
        }
    }

    public function getSupportedCountries(): array
    {
        try {
            $response = Http::withToken($this->secretKey)->get($this->baseUrl.'/country');

            if (! $response->successful()) {
                throw new \Exception('Failed to fetch countries: '.$response->body());
            }

            $payload = $response->json();

            if (! ($payload['status'] ?? false)) {
                throw new \Exception('Failed to fetch countries: '.($payload['message'] ?? 'Unknown error'));
            }

            return collect($payload['data'] ?? [])
                ->filter(fn (array $country): bool => ! empty($country['iso_code']) && ! empty($country['name']))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('Failed to fetch supported countries from Paystack', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getSupportedCurrencies(?string $countryCode = null): array
    {
        $countries = $this->getSupportedCountries();

        if ($countryCode) {
            $country = collect($countries)->firstWhere('iso_code', strtoupper($countryCode));

            return collect(data_get($country, 'relationships.currency.data', []))
                ->filter(fn (mixed $currency): bool => is_string($currency) && $currency !== '')
                ->map(fn (string $currency): string => strtoupper($currency))
                ->unique()
                ->values()
                ->all();
        }

        return collect($countries)
            ->flatMap(fn (array $country): array => data_get($country, 'relationships.currency.data', []))
            ->filter(fn (mixed $currency): bool => is_string($currency) && $currency !== '')
            ->map(fn (string $currency): string => strtoupper($currency))
            ->unique()
            ->values()
            ->all();
    }

    public function createCheckoutSession(Booking $booking, PaymentConfiguration $config): array
    {
        try {
            $workspace = $booking->product->workspace;
            $currency = (string) $workspace->currency;
            $platformFeePercentage = (float) $config->platform_fee_percentage;
            $platformFeeAmount = round((float) $booking->amount_paid * ($platformFeePercentage / 100), 2);
            $creatorPayoutEstimate = max(round((float) $booking->amount_paid - $platformFeeAmount, 2), 0);
            $reference = 'SPONSOR_'.$booking->id.'_'.Str::random(8);

            $payload = [
                'email' => $booking->guest_email ?? $booking->brandUser?->email,
                'amount' => $this->convertToSmallestUnit((float) $booking->amount_paid, $currency),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => route('payment.paystack.callback'),
                'subaccount' => $config->provider_account_id,
                'bearer' => 'account',
                'transaction_charge' => $this->convertToSmallestUnit($platformFeeAmount, $currency),
                'metadata' => [
                    'booking_id' => $booking->id,
                    'workspace_id' => $config->workspace_id,
                    'creator_id' => $booking->creator_id,
                    'product_id' => $booking->product_id,
                    'slot_id' => $booking->slot_id,
                    'currency' => $currency,
                    'country' => $workspace->country_code,
                ],
                'channels' => $this->getAvailableChannels((string) $workspace->country_code),
            ];

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl.'/transaction/initialize', $payload);

            if (! $response->successful()) {
                throw new \Exception('Paystack API error: '.$response->body());
            }

            $data = $response->json();

            if (! ($data['status'] ?? false)) {
                throw new \Exception('Paystack transaction initialization failed: '.($data['message'] ?? 'Unknown error'));
            }

            $payment = BookingPayment::createForBooking($booking, 'paystack', [
                'provider_reference' => $reference,
                'session_id' => $data['data']['access_code'],
                'currency' => $currency,
                'metadata' => [
                    'workspace_id' => $config->workspace_id,
                    'creator_id' => $booking->creator_id,
                    'product_id' => $booking->product_id,
                    'slot_id' => $booking->slot_id,
                    'country' => $workspace->country_code,
                ],
                'provider_data' => [
                    'access_code' => $data['data']['access_code'],
                    'authorization_url' => $data['data']['authorization_url'],
                    'platform_fee_percentage' => $platformFeePercentage,
                    'platform_fee_amount' => $platformFeeAmount,
                    'creator_percentage' => 100 - $platformFeePercentage,
                    'creator_payout_estimate' => $creatorPayoutEstimate,
                    'subaccount_code' => $config->provider_account_id,
                    'currency' => $currency,
                ],
            ]);

            return [
                'id' => $data['data']['access_code'],
                'url' => $data['data']['authorization_url'],
                'reference' => $reference,
                'payment_id' => $payment->id,
            ];
        } catch (\Exception $e) {
            Log::error('Paystack checkout session creation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment session: '.$e->getMessage());
        }
    }

    public function handleSuccessfulPayment(string $paymentReference): void
    {
        try {
            $verificationData = $this->verifyPaystackTransaction($paymentReference);
            $payment = $this->findPaystackPaymentWithRelations($paymentReference);

            if (! $payment) {
                Log::error('Payment record not found for Paystack reference', [
                    'reference' => $paymentReference,
                ]);

                return;
            }

            $paidAmount = $this->convertFromSmallestUnit((int) $verificationData['amount'], (string) $payment->currency);
            $localBreakdown = $this->buildLocalBreakdown($payment, $verificationData, $paidAmount);
            $conversion = $this->normalizeUsdConversion(
                $this->resolveUsdConversion($payment, $paidAmount, $paymentReference),
                $paidAmount
            );

            $payment->update([
                'provider_transaction_id' => $verificationData['id'],
                'status' => 'completed',
                'paid_at' => now(),
                'amount' => $paidAmount,
                'amount_usd' => $conversion['amount_usd'],
                'exchange_rate_to_usd' => $conversion['exchange_rate_to_usd'],
                'exchange_rate_provider' => $conversion['provider'],
                'exchange_rate_fetched_at' => $conversion['fetched_at'],
                'amount_breakdown' => [
                    'local' => $localBreakdown,
                    'usd' => $this->convertBreakdownToUsd($localBreakdown, (float) ($conversion['exchange_rate_to_usd'] ?? 0)),
                ],
                'provider_data' => array_merge(
                    $payment->provider_data ?? [],
                    [
                        'transaction_data' => $verificationData,
                        'verified_at' => now()->toISOString(),
                    ]
                ),
            ]);

            $booking = $payment->booking;

            if (! $booking) {
                return;
            }

            $booking->update(['status' => BookingStatus::CONFIRMED]);

            if ($booking->slot) {
                $booking->slot->update([
                    'status' => SlotStatus::Booked,
                    'reserved_until' => null,
                ]);
            }

            if ($booking->isMarketplaceApplication() && $booking->campaignSlot) {
                $booking->campaignSlot->update([
                    'status' => CampaignSlotStatus::Active,
                ]);
            }

            $this->handleGuestAccountCreation($booking);
            app(BookingShadowCampaignService::class)->activateForPaidInquiry($booking->fresh());
            $this->notifyCreatorPaymentReceived($booking, $payment);

            Log::info('Paystack payment confirmed for booking', [
                'booking_id' => $payment->booking_id,
                'reference' => $paymentReference,
                'transaction_id' => $verificationData['id'],
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to handle successful Paystack payment', [
                'reference' => $paymentReference,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to process payment confirmation: '.$e->getMessage());
        }
    }

    public function createConnectAccount(Workspace $workspace, array $bankDetails = []): array
    {
        try {
            $this->assertValidBankDetails($bankDetails);
            $owner = $this->resolveWorkspaceOwner($workspace);
            $existingConfig = $this->findWorkspaceProviderConfig($workspace);

            if ($existingConfig?->provider_account_id) {
                throw new \Exception('Paystack subaccount already exists. Use update instead.');
            }

            $platformFeePercentage = $this->resolvePlatformFeePercentage($existingConfig);
            [$subaccountCode, $subaccountId, $businessName, $percentageCharge, $settlementSchedule] =
                $this->createSubaccount($workspace, $owner, $bankDetails, $platformFeePercentage);

            $this->persistPaymentConfiguration(
                $workspace,
                $subaccountCode,
                $subaccountId,
                $businessName,
                $percentageCharge,
                $settlementSchedule,
                $platformFeePercentage,
                $bankDetails
            );

            return [
                'account_id' => $subaccountCode,
                'account_name' => $bankDetails['account_name'],
                'onboarding_url' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Paystack subaccount', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception($e->getMessage());
        }
    }

    public function updateConnectAccount(Workspace $workspace, array $bankDetails = []): array
    {
        try {
            $this->assertValidBankDetails($bankDetails);
            $owner = $this->resolveWorkspaceOwner($workspace);
            $existingConfig = $this->findWorkspaceProviderConfig($workspace);

            if (! $existingConfig || ! $existingConfig->provider_account_id) {
                throw new \Exception('No existing Paystack subaccount found. Create one first.');
            }

            $platformFeePercentage = $this->resolvePlatformFeePercentage($existingConfig);
            $subaccountCode = (string) $existingConfig->provider_account_id;

            $updatePayload = [
                'business_name' => $workspace->name,
                'bank_code' => $bankDetails['bank_code'],
                'account_number' => $bankDetails['account_number'],
                'percentage_charge' => $platformFeePercentage,
                'description' => 'Creator subaccount for '.$workspace->name,
                'primary_contact_email' => $owner->email,
                'primary_contact_name' => $owner->name,
                'settlement_schedule' => 'manual',
                'active' => true,
            ];

            $updateResponse = $this->updateSubaccount($subaccountCode, $updatePayload);

            if (! $updateResponse->successful()) {
                throw new \Exception('Paystack subaccount update API error: '.$updateResponse->body());
            }

            $updateData = $updateResponse->json();

            if (! ($updateData['status'] ?? false)) {
                throw new \Exception('Paystack subaccount update failed: '.($updateData['message'] ?? 'Unknown error'));
            }

            $subaccountId = $updateData['data']['id'] ?? data_get($existingConfig->provider_data, 'subaccount_id');
            $businessName = $updateData['data']['business_name'] ?? $workspace->name;
            $percentageCharge = $updateData['data']['percentage_charge'] ?? $platformFeePercentage;
            $settlementSchedule = $updateData['data']['settlement_schedule'] ?? 'manual';

            $this->persistPaymentConfiguration(
                $workspace,
                $subaccountCode,
                $subaccountId,
                $businessName,
                $percentageCharge,
                $settlementSchedule,
                $platformFeePercentage,
                $bankDetails
            );

            return [
                'account_id' => $subaccountCode,
                'account_name' => $bankDetails['account_name'],
                'onboarding_url' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update Paystack subaccount', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception($e->getMessage());
        }
    }

    public function getOnboardingUrl(PaymentConfiguration $config): ?string
    {
        return null;
    }

    public function isAccountVerified(PaymentConfiguration $config): bool
    {
        if (! $config->provider_account_id) {
            return false;
        }

        return (bool) $config->is_verified;
    }

    public function releaseFunds(Booking $booking): bool
    {
        try {
            $workspace = $booking->product->workspace;
            $config = $this->findActiveWorkspaceProviderConfig($workspace);

            if (! $config) {
                throw new \Exception("No active Paystack configuration found for workspace {$workspace->id}");
            }

            $payment = $booking->getPaymentFor('paystack');

            if (! $payment || ! $payment->isCompleted()) {
                throw new \Exception('No completed Paystack payment found for booking');
            }

            if ($payment->creator_released_at) {
                Log::warning('Attempted to release Paystack funds more than once', [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                ]);

                return false;
            }

            $localBreakdown = data_get($payment->amount_breakdown, 'local', []);
            $platformFeeAmount = (float) data_get(
                $localBreakdown,
                'platform_fee_amount',
                round((float) $booking->amount_paid * ((float) $config->platform_fee_percentage / 100), 2)
            );
            $creatorAmount = (float) data_get(
                $localBreakdown,
                'creator_payout_amount',
                max(round((float) $payment->amount - $platformFeeAmount, 2), 0)
            );

            $recipientCode = $this->createTransferRecipient($config, (string) $workspace->currency);

            $payload = [
                'source' => 'balance',
                'amount' => $this->convertToSmallestUnit($creatorAmount, (string) $workspace->currency),
                'recipient' => $recipientCode,
                'reason' => "Sponsorship payout for booking #{$booking->id}",
                'currency' => $workspace->currency,
                'reference' => 'RELEASE_'.$booking->id.'_'.Str::random(8),
            ];

            $response = Http::withToken($this->secretKey)->post($this->baseUrl.'/transfer', $payload);

            if (! $response->successful()) {
                throw new \Exception('Paystack transfer API error: '.$response->body());
            }

            $data = $response->json();

            if (! ($data['status'] ?? false)) {
                throw new \Exception('Paystack transfer failed: '.($data['message'] ?? 'Unknown error'));
            }

            $updatedBreakdown = $payment->amount_breakdown ?? [];
            data_set($updatedBreakdown, 'local.creator_released_amount', $creatorAmount);
            data_set($updatedBreakdown, 'usd.creator_released_amount', round($creatorAmount * (float) ($payment->exchange_rate_to_usd ?? 0), 2));

            $payment->update([
                'creator_released_at' => now(),
                'amount_breakdown' => $updatedBreakdown,
                'provider_data' => array_merge(
                    $payment->provider_data ?? [],
                    [
                        'funds_released' => true,
                        'released_at' => now()->toISOString(),
                        'release_transfer_data' => $data['data'],
                    ]
                ),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to release funds', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function refundPayment(Booking $booking, string $reason = 'Work rejected'): bool
    {
        try {
            $payment = $booking->getPaymentFor('paystack');

            if (! $payment || ! $payment->provider_transaction_id) {
                throw new \Exception('No Paystack payment found for booking');
            }

            $payload = [
                'transaction' => $payment->provider_transaction_id,
                'amount' => $this->convertToSmallestUnit((float) $payment->amount, (string) $payment->currency),
                'currency' => $payment->currency,
                'customer_note' => $reason,
                'merchant_note' => "Refund for booking #{$booking->id}: {$reason}",
            ];

            $response = Http::withToken($this->secretKey)->post($this->baseUrl.'/refund', $payload);

            if (! $response->successful()) {
                throw new \Exception('Paystack refund API error: '.$response->body());
            }

            $data = $response->json();

            if (! ($data['status'] ?? false)) {
                throw new \Exception('Paystack refund failed: '.($data['message'] ?? 'Unknown error'));
            }

            $updatedBreakdown = $payment->amount_breakdown ?? [];
            $localGrossAmount = (float) data_get($updatedBreakdown, 'local.gross_amount', $payment->amount);
            $localPlatformFeeAmount = (float) data_get($updatedBreakdown, 'local.platform_fee_amount', 0);
            $localCreatorPayoutAmount = (float) data_get($updatedBreakdown, 'local.creator_payout_amount', 0);
            $exchangeRateToUsd = (float) ($payment->exchange_rate_to_usd ?? 0);

            data_set($updatedBreakdown, 'local.refunded_amount', $localGrossAmount);
            data_set($updatedBreakdown, 'local.platform_fee_refunded_amount', $localPlatformFeeAmount);
            data_set($updatedBreakdown, 'local.creator_payout_reversed_amount', $localCreatorPayoutAmount);
            data_set($updatedBreakdown, 'local.refund_reason', $reason);

            if ($exchangeRateToUsd > 0) {
                data_set($updatedBreakdown, 'usd.refunded_amount', round($localGrossAmount * $exchangeRateToUsd, 2));
                data_set($updatedBreakdown, 'usd.platform_fee_refunded_amount', round($localPlatformFeeAmount * $exchangeRateToUsd, 2));
                data_set($updatedBreakdown, 'usd.creator_payout_reversed_amount', round($localCreatorPayoutAmount * $exchangeRateToUsd, 2));
                data_set($updatedBreakdown, 'usd.refund_reason', $reason);
            }

            $payment->markAsRefunded($reason);
            $payment->update([
                'amount_breakdown' => $updatedBreakdown,
                'provider_data' => array_merge(
                    $payment->provider_data ?? [],
                    [
                        'refund_data' => $data['data'] ?? null,
                        'refund_reason' => $reason,
                        'refunded_at' => now()->toISOString(),
                    ]
                ),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to process refund', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getSupportedBanks(string $countryCode = 'NG', ?string $currency = null, ?string $type = null): array
    {
        try {
            $country = [
                'NG' => 'nigeria',
                'GH' => 'ghana',
                'ZA' => 'south africa',
                'KE' => 'kenya',
            ][$countryCode] ?? 'nigeria';

            $query = ['country' => $country];

            if ($currency) {
                $query['currency'] = strtoupper($currency);
            }

            if ($type) {
                $query['type'] = $type;
            }

            $response = Http::withToken($this->secretKey)->get($this->baseUrl.'/bank', $query);

            if (! $response->successful()) {
                throw new \Exception('Failed to fetch banks: '.$response->body());
            }

            $data = $response->json();

            if (! ($data['status'] ?? false)) {
                throw new \Exception('Failed to fetch banks: '.($data['message'] ?? 'Unknown error'));
            }

            return collect($data['data'])
                ->filter(fn (array $bank): bool => (bool) ($bank['active'] ?? true))
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('Failed to fetch supported banks', [
                'country_code' => $countryCode,
                'currency' => $currency,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function verifyBankAccount(string $accountNumber, string $bankCode): array
    {
        $response = Http::withToken($this->secretKey)
            ->get($this->baseUrl.'/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        if (! $response->successful()) {
            $body = $response->body();
            $json = json_decode($body, true);
            $message = is_array($json) && isset($json['message']) ? $json['message'] : $body;

            throw new \Exception('Bank account verification failed: '.$message);
        }

        $data = $response->json();

        if (! ($data['status'] ?? false)) {
            throw new \Exception('Invalid bank account details: '.($data['message'] ?? 'Unknown error'));
        }

        return $data['data'];
    }

    protected function persistPaymentConfiguration(
        Workspace $workspace,
        string $subaccountCode,
        mixed $subaccountId,
        string $businessName,
        mixed $percentageCharge,
        string $settlementSchedule,
        float $platformFeePercentage,
        array $bankDetails
    ): void {
        PaymentConfiguration::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $workspace->owner_id,
                'provider' => 'paystack',
            ],
            [
                'provider_account_id' => $subaccountCode,
                'is_active' => true,
                'is_verified' => true,
                'verified_at' => now(),
                'platform_fee_percentage' => $platformFeePercentage,
                'provider_data' => [
                    'subaccount_id' => $subaccountId,
                    'business_name' => $businessName,
                    'account_name' => $bankDetails['account_name'],
                    'bank_code' => $bankDetails['bank_code'],
                    'bank_type' => $bankDetails['bank_type'] ?? null,
                    'payment_method' => $bankDetails['payment_method'] ?? null,
                    'percentage_charge' => $percentageCharge,
                    'settlement_schedule' => $settlementSchedule,
                    'bank_name' => $bankDetails['bank_name'] ?? $bankDetails['account_name'],
                ],
            ]
        );
    }

    protected function createSubaccount(Workspace $workspace, User $owner, array $bankDetails, float $platformFeePercentage): array
    {
        $createPayload = [
            'business_name' => $workspace->name,
            'settlement_bank' => $bankDetails['bank_code'],
            'account_number' => $bankDetails['account_number'],
            'percentage_charge' => $platformFeePercentage,
            'description' => 'Creator subaccount for '.$workspace->name,
            'primary_contact_email' => $owner->email,
            'primary_contact_name' => $owner->name,
            'settlement_schedule' => 'manual',
        ];

        $createResponse = Http::withToken($this->secretKey)
            ->post($this->baseUrl.'/subaccount', $createPayload);

        if (! $createResponse->successful()) {
            throw new \Exception('Paystack subaccount creation API error: '.$createResponse->body());
        }

        $createData = $createResponse->json();

        if (! ($createData['status'] ?? false)) {
            throw new \Exception('Paystack subaccount creation failed: '.($createData['message'] ?? 'Unknown error'));
        }

        return [
            $createData['data']['subaccount_code'],
            $createData['data']['id'],
            $createData['data']['business_name'],
            $createData['data']['percentage_charge'],
            $createData['data']['settlement_schedule'],
        ];
    }

    protected function updateSubaccount(string $subaccountCode, array $updatePayload)
    {
        return Http::withToken($this->secretKey)
            ->put($this->baseUrl.'/subaccount/'.$subaccountCode, $updatePayload);
    }

    protected function resolvePlatformFeePercentage(?PaymentConfiguration $existingConfig): float
    {
        $percentage = $existingConfig?->platform_fee_percentage;

        if ($percentage === null) {
            $percentage = 10;
        }

        return round(max(0, min(100, (float) $percentage)), 4);
    }

    protected function resolveUsdConversion(BookingPayment $payment, float $paidAmount, string $reference): array
    {
        if (strtoupper((string) $payment->currency) === 'USD') {
            return [
                'amount_usd' => $paidAmount,
                'exchange_rate_to_usd' => 1.0,
                'provider' => 'identity',
                'fetched_at' => now(),
            ];
        }

        $storedRate = (float) ($payment->exchange_rate_to_usd ?? 0);

        if ($storedRate > 0) {
            return [
                'amount_usd' => round($paidAmount * $storedRate, 2),
                'exchange_rate_to_usd' => $storedRate,
                'provider' => $payment->exchange_rate_provider,
                'fetched_at' => $payment->exchange_rate_fetched_at,
            ];
        }

        try {
            return app(ExchangeRateService::class)->convertToUsd($paidAmount, (string) $payment->currency);
        } catch (\Throwable $exception) {
            Log::warning('Unable to convert Paystack payment amount to USD via configured provider', [
                'payment_id' => $payment->id,
                'reference' => $reference,
                'currency' => $payment->currency,
                'error' => $exception->getMessage(),
            ]);
        }

        $fallbackRate = ExchangeRate::query()
            ->where('base_currency', strtoupper((string) $payment->currency))
            ->where('target_currency', 'USD')
            ->latest('fetched_at')
            ->first();

        if ($fallbackRate && (float) $fallbackRate->rate > 0) {
            $rate = (float) $fallbackRate->rate;

            return [
                'amount_usd' => round($paidAmount * $rate, 2),
                'exchange_rate_to_usd' => $rate,
                'provider' => $fallbackRate->provider,
                'fetched_at' => $fallbackRate->fetched_at,
            ];
        }

        return [
            'amount_usd' => $payment->amount_usd,
            'exchange_rate_to_usd' => $payment->exchange_rate_to_usd,
            'provider' => $payment->exchange_rate_provider,
            'fetched_at' => $payment->exchange_rate_fetched_at,
        ];
    }

    protected function createTransferRecipient(PaymentConfiguration $config, string $currency): string
    {
        $providerData = $config->provider_data;

        if (isset($providerData['recipient_code'])) {
            return $providerData['recipient_code'];
        }

        $subaccountResponse = Http::withToken($this->secretKey)
            ->get($this->baseUrl.'/subaccount/'.$config->provider_account_id);

        if (! $subaccountResponse->successful()) {
            throw new \Exception('Failed to fetch subaccount from Paystack: '.$subaccountResponse->body());
        }

        $subaccountData = $subaccountResponse->json();

        if (! ($subaccountData['status'] ?? false)) {
            throw new \Exception('Paystack subaccount fetch failed: '.($subaccountData['message'] ?? 'Unknown error'));
        }

        $subaccount = $subaccountData['data'];
        $banks = $this->getSupportedBanks($this->resolveBankCountry($currency));
        $bankCollection = collect($banks);

        $bank = isset($subaccount['bank_id'])
            ? $bankCollection->firstWhere('id', $subaccount['bank_id'])
            : null;

        if (! $bank && isset($subaccount['settlement_bank'])) {
            $bank = $bankCollection->firstWhere('name', $subaccount['settlement_bank']);
        }

        if (! $bank) {
            throw new \Exception(
                'Could not resolve bank code for subaccount '.$config->provider_account_id.
                '. settlement_bank='.($subaccount['settlement_bank'] ?? 'unknown')
            );
        }

        $recipientType = $this->resolveTransferRecipientType(
            $providerData,
            is_array($bank) ? $bank : [],
            (string) ($subaccount['currency'] ?? $currency)
        );

        $payload = [
            'type' => $recipientType,
            'name' => $subaccount['account_name'] ?? $providerData['business_name'],
            'account_number' => $subaccount['account_number'],
            'bank_code' => $bank['code'],
            'currency' => $subaccount['currency'] ?? $currency,
        ];

        $response = Http::withToken($this->secretKey)
            ->post($this->baseUrl.'/transferrecipient', $payload);

        if (! $response->successful()) {
            throw new \Exception('Transfer recipient creation failed: '.$response->body());
        }

        $data = $response->json();

        if (! ($data['status'] ?? false)) {
            throw new \Exception('Transfer recipient creation failed: '.($data['message'] ?? 'Unknown error'));
        }

        $recipientCode = $data['data']['recipient_code'];

        $config->update([
            'provider_data' => array_merge($providerData, [
                'recipient_code' => $recipientCode,
            ]),
        ]);

        return $recipientCode;
    }

    protected function resolveBankCountry(string $currency): string
    {
        return match (strtoupper($currency)) {
            'GHS' => 'GH',
            'ZAR' => 'ZA',
            'KES' => 'KE',
            default => 'NG',
        };
    }

    protected function resolveTransferRecipientType(array $providerData, array $bank, string $currency): string
    {
        $storedType = data_get($providerData, 'bank_type');

        if (is_string($storedType) && $storedType !== '') {
            return $storedType;
        }

        $bankType = data_get($bank, 'type');

        if (is_string($bankType) && $bankType !== '') {
            return $bankType;
        }

        $paymentMethod = (string) data_get($providerData, 'payment_method', '');

        if (str_contains($paymentMethod, 'mobile_money')) {
            return 'mobile_money';
        }

        return match (strtoupper($currency)) {
            'GHS' => 'ghipss',
            'ZAR' => 'basa',
            default => 'nuban',
        };
    }

    protected function convertBreakdownToUsd(array $localBreakdown, float $exchangeRateToUsd): array
    {
        $numericKeys = [
            'gross_amount',
            'platform_fee_amount',
            'processor_fee_amount',
            'escrow_amount',
            'creator_payout_amount',
            'requested_amount',
        ];

        $usdBreakdown = ['currency' => 'USD'];

        foreach ($numericKeys as $key) {
            $value = data_get($localBreakdown, $key);

            if ($value === null || ! is_numeric($value) || $exchangeRateToUsd <= 0) {
                $usdBreakdown[$key] = null;

                continue;
            }

            $usdBreakdown[$key] = round((float) $value * $exchangeRateToUsd, 2);
        }

        return $usdBreakdown;
    }

    protected function convertToSmallestUnit(float $amount, string $currency): int
    {
        return (int) ($amount * 100);
    }

    protected function convertFromSmallestUnit(int $amount, string $currency): float
    {
        return $amount / 100;
    }

    protected function getAvailableChannels(string $countryCode): array
    {
        return ['card', 'bank', 'apple_pay', 'ussd', 'qr', 'mobile_money', 'bank_transfer', 'eft', 'capitec_pay', 'payattitude'];
    }

    private function findWorkspaceProviderConfig(Workspace $workspace): ?PaymentConfiguration
    {
        return PaymentConfiguration::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $workspace->owner_id)
            ->where('provider', 'paystack')
            ->first();
    }

    private function findActiveWorkspaceProviderConfig(Workspace $workspace): ?PaymentConfiguration
    {
        return PaymentConfiguration::query()
            ->where('workspace_id', $workspace->id)
            ->where('provider', 'paystack')
            ->where('is_active', true)
            ->first();
    }

    private function resolveWorkspaceOwner(Workspace $workspace): User
    {
        $owner = $workspace->owner;

        if (! $owner) {
            throw new \Exception('No workspace owner found');
        }

        return $owner;
    }

    private function assertValidBankDetails(array $bankDetails): void
    {
        if (empty($bankDetails)) {
            throw new \Exception('Bank details are required');
        }

        if (! isset($bankDetails['account_number'], $bankDetails['bank_code'], $bankDetails['account_name'])) {
            throw new \Exception('Account number, bank code, and account name are required');
        }
    }

    private function verifyPaystackTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get($this->baseUrl.'/transaction/verify/'.$reference);

        if (! $response->successful()) {
            throw new \Exception('Paystack verification API error: '.$response->body());
        }

        $data = $response->json();

        if (! ($data['status'] ?? false) || ($data['data']['status'] ?? null) !== 'success') {
            throw new \Exception('Payment verification failed');
        }

        return $data['data'];
    }

    private function findPaystackPaymentWithRelations(string $reference): ?BookingPayment
    {
        return BookingPayment::query()
            ->with(['booking.slot', 'booking.product', 'booking.creator'])
            ->where('provider', 'paystack')
            ->where('provider_reference', $reference)
            ->first();
    }

    private function buildLocalBreakdown(BookingPayment $payment, array $transactionData, float $paidAmount): array
    {
        $platformFeePercentage = (float) data_get($payment->provider_data, 'platform_fee_percentage', 0);
        $platformFeeAmount = round($paidAmount * ($platformFeePercentage / 100), 2);
        $processorFeeAmount = isset($transactionData['fees'])
            ? $this->convertFromSmallestUnit((int) $transactionData['fees'], (string) $payment->currency)
            : null;
        $escrowAmount = max(round($paidAmount - $platformFeeAmount, 2), 0);

        return [
            'currency' => $payment->currency,
            'gross_amount' => $paidAmount,
            'platform_fee_amount' => $platformFeeAmount,
            'processor_fee_amount' => $processorFeeAmount,
            'escrow_amount' => $escrowAmount,
            'creator_payout_amount' => $escrowAmount,
            'requested_amount' => isset($transactionData['requested_amount'])
                ? $this->convertFromSmallestUnit((int) $transactionData['requested_amount'], (string) $payment->currency)
                : null,
        ];
    }

    private function normalizeUsdConversion(array $conversion, float $paidAmount): array
    {
        $effectiveRate = (float) ($conversion['exchange_rate_to_usd'] ?? 0);
        $amountUsd = $conversion['amount_usd'] ?? null;

        if ($effectiveRate <= 0 && is_numeric($amountUsd) && $paidAmount > 0) {
            $effectiveRate = round(((float) $amountUsd) / $paidAmount, 10);
            $conversion['exchange_rate_to_usd'] = $effectiveRate;
        }

        if (! is_numeric($amountUsd) && $effectiveRate > 0) {
            $conversion['amount_usd'] = round($paidAmount * $effectiveRate, 2);
        }

        return $conversion;
    }

    private function notifyCreatorPaymentReceived(Booking $booking, BookingPayment $payment): void
    {
        if (! $booking->creator) {
            return;
        }

        try {
            $booking->creator->notify(new PaymentReceivedNotification($booking));
        } catch (\Throwable $exception) {
            Log::warning('Failed to dispatch creator payment notification', [
                'booking_id' => $payment->booking_id,
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function handleGuestAccountCreation(Booking $booking): void
    {
        try {
            if (! $booking->brand_user_id && $booking->guest_email && $booking->guest_name) {
                $guestAccountService = app(GuestAccountCreationService::class);
                $guestAccountService->createAccountForGuest($booking);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle guest account creation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
