<?php

namespace App\Services\Providers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentConfiguration;
use App\Models\Workspace;
use App\Notifications\PaymentReceivedNotification;
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
        $this->secretKey = config('services.paystack.secret_key');

        if (! $this->secretKey) {
            throw new \Exception('Paystack secret key not configured');
        }
    }

    public function createCheckoutSession(Booking $booking, PaymentConfiguration $config): array
    {
        try {
            $workspace = $booking->product->workspace;
            $currency = $workspace->currency;
            $platformFeePercentage = $config->platform_fee_percentage;
            $creatorPercentage = 100 - $platformFeePercentage;

            $reference = 'SPONSOR_'.$booking->id.'_'.Str::random(8);

            $payload = [
                'email' => $booking->guest_email ?? $booking->brandUser->email,
                'amount' => $this->convertToSmallestUnit($booking->amount_paid, $currency),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => route('payment.paystack.callback'),
                'subaccount' => $config->provider_account_id,
                'bearer' => 'subaccount',
                'transaction_charge' => $this->convertToSmallestUnit(
                    $booking->amount_paid * ($platformFeePercentage / 100),
                    $currency
                ),
                'metadata' => [
                    'booking_id' => $booking->id,
                    'workspace_id' => $config->workspace_id,
                    'creator_id' => $booking->creator_id,
                    'product_id' => $booking->product_id,
                    'slot_id' => $booking->slot_id,
                    'currency' => $currency,
                    'country' => $workspace->country_code,
                ],
                'channels' => $this->getAvailableChannels($workspace->country_code),
            ];

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl.'/transaction/initialize', $payload);

            if (! $response->successful()) {
                throw new \Exception('Paystack API error: '.$response->body());
            }

            $data = $response->json();

            if (! $data['status']) {
                throw new \Exception('Paystack transaction initialization failed: '.$data['message']);
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
                    'creator_percentage' => $creatorPercentage,
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

    public function handleSuccessfulPayment(string $reference): void
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->get($this->baseUrl.'/transaction/verify/'.$reference);

            if (! $response->successful()) {
                throw new \Exception('Paystack verification API error: '.$response->body());
            }

            $data = $response->json();

            if (! $data['status'] || $data['data']['status'] !== 'success') {
                throw new \Exception('Payment verification failed');
            }

            $payment = BookingPayment::findByProviderReference('paystack', $reference);

            if ($payment) {
                $payment->update([
                    'provider_transaction_id' => $data['data']['id'],
                    'status' => 'completed',
                    'paid_at' => now(),
                    'amount' => $this->convertFromSmallestUnit($data['data']['amount'], $payment->currency),
                    'provider_data' => array_merge(
                        $payment->provider_data ?? [],
                        [
                            'transaction_data' => $data['data'],
                            'verified_at' => now()->toISOString(),
                        ]
                    ),
                ]);

                $payment->booking->update([
                    'status' => BookingStatus::CONFIRMED,
                ]);

                $slot = $payment->booking->slot;
                if ($slot) {
                    $slot->update([
                        'status' => \App\Enums\SlotStatus::Booked,
                        'reserved_until' => null,
                    ]);
                }

                $this->handleGuestAccountCreation($payment->booking);

                $booking = $payment->booking->load(['product', 'creator']);
                if ($booking->creator) {
                    $booking->creator->notify(new PaymentReceivedNotification($booking));
                }

                Log::info('Paystack payment confirmed for booking', [
                    'booking_id' => $payment->booking_id,
                    'reference' => $reference,
                    'transaction_id' => $data['data']['id'],
                    'payment_id' => $payment->id,
                ]);
            } else {
                Log::error('Payment record not found for Paystack reference', [
                    'reference' => $reference,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle successful Paystack payment', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to process payment confirmation: '.$e->getMessage());
        }
    }

    public function createConnectAccount(Workspace $workspace, array $bankDetails = []): array
    {
        try {
            if (empty($bankDetails)) {
                throw new \Exception('Bank details required for subaccount creation');
            }
            if (! isset($workspace)) {
                $workspace = currentWorkspace();
            }

            if (! isset($bankDetails['account_number']) || ! isset($bankDetails['bank_code']) || ! isset($bankDetails['account_name'])) {
                throw new \Exception('Account number, bank code, and account name are required');
            }

            $owner = $workspace->owner;
            if (! $owner) {
                throw new \Exception('No workspace owner found');
            }

            $existingConfig = PaymentConfiguration::where([
                'workspace_id' => $workspace->id,
                'user_id' => $workspace->owner_id,
                'provider' => 'paystack',
            ])->first();

            $subaccountCode = null;
            $subaccountId = null;
            $businessName = $workspace->name;
            $percentageCharge = 90;
            $settlementSchedule = 'manual';

            if ($existingConfig && $existingConfig->provider_account_id) {
                $subaccountCode = $existingConfig->provider_account_id;

                $updatePayload = [
                    'business_name' => $workspace->name,
                    'settlement_bank' => $bankDetails['bank_code'],
                    'account_number' => $bankDetails['account_number'],
                    'percentage_charge' => 90,
                    'description' => 'Creator subaccount for '.$workspace->name,
                    'primary_contact_email' => $owner->email,
                    'primary_contact_name' => $owner->name,
                    'settlement_schedule' => 'manual',
                ];

                $updateResponse = Http::withToken($this->secretKey)
                    ->put($this->baseUrl.'/subaccount/'.$subaccountCode, $updatePayload);

                if (! $updateResponse->successful()) {
                    throw new \Exception('Paystack subaccount update API error: '.$updateResponse->body());
                }

                $updateData = $updateResponse->json();

                if (! $updateData['status']) {
                    throw new \Exception('Paystack subaccount update failed: '.$updateData['message']);
                }

                $subaccountId = $updateData['data']['id'] ?? $existingConfig->provider_data['subaccount_id'] ?? null;
                $businessName = $updateData['data']['business_name'] ?? $workspace->name;
                $percentageCharge = $updateData['data']['percentage_charge'] ?? 90;
                $settlementSchedule = $updateData['data']['settlement_schedule'] ?? 'manual';

                Log::info('Paystack subaccount updated', [
                    'workspace_id' => $workspace->id,
                    'subaccount_code' => $subaccountCode,
                ]);
            } else {
                $createPayload = [
                    'business_name' => $workspace->name,
                    'settlement_bank' => $bankDetails['bank_code'],
                    'account_number' => $bankDetails['account_number'],
                    'percentage_charge' => 90,
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

                if (! $createData['status']) {
                    throw new \Exception('Paystack subaccount creation failed: '.$createData['message']);
                }

                $subaccountCode = $createData['data']['subaccount_code'];
                $subaccountId = $createData['data']['id'];
                $businessName = $createData['data']['business_name'];
                $percentageCharge = $createData['data']['percentage_charge'];
                $settlementSchedule = $createData['data']['settlement_schedule'];

                Log::info('Paystack subaccount created', [
                    'workspace_id' => $workspace->id,
                    'subaccount_code' => $subaccountCode,
                ]);
            }

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
                    'provider_data' => [
                        'subaccount_id' => $subaccountId,
                        'business_name' => $businessName,
                        'account_name' => $bankDetails['account_name'],
                        'percentage_charge' => $percentageCharge,
                        'settlement_schedule' => $settlementSchedule,
                        'bank_name' => $this->getBankName($bankDetails['bank_code']),
                    ],
                ]
            );

            return [
                'account_id' => $subaccountCode,
                'account_name' => $bankDetails['account_name'],
                'onboarding_url' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create/update Paystack subaccount', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create payment account: '.$e->getMessage());
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

        return $config->is_verified;
    }

    public function releaseFunds(Booking $booking): bool
    {
        try {
            $workspace = $booking->product->workspace;

            $config = PaymentConfiguration::where('workspace_id', $workspace->id)
                ->where('provider', 'paystack')
                ->where('is_active', true)
                ->first();

            if (! $config) {
                throw new \Exception("No active Paystack configuration found for workspace {$workspace->id}");
            }

            $platformFeeAmount = $booking->amount_paid * ($config->platform_fee_percentage / 100);
            $creatorAmount = $booking->amount_paid - $platformFeeAmount;

            $recipientCode = $this->createTransferRecipient($config, $workspace->currency);

            $payload = [
                'source' => 'balance',
                'amount' => $this->convertToSmallestUnit($creatorAmount, $workspace->currency),
                'recipient' => $recipientCode,
                'reason' => "Sponsorship payout for booking #{$booking->id}",
                'currency' => $workspace->currency,
                'reference' => 'RELEASE_'.$booking->id.'_'.Str::random(8),
            ];

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl.'/transfer', $payload);

            if (! $response->successful()) {
                throw new \Exception('Paystack transfer API error: '.$response->body());
            }

            $data = $response->json();

            if (! $data['status']) {
                throw new \Exception('Paystack transfer failed: '.$data['message']);
            }

            Log::info('Funds released to creator', [
                'booking_id' => $booking->id,
                'transfer_code' => $data['data']['transfer_code'],
                'amount' => $creatorAmount,
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
                'amount' => $this->convertToSmallestUnit($payment->amount, $payment->currency),
                'currency' => $payment->currency,
                'customer_note' => $reason,
                'merchant_note' => "Refund for booking #{$booking->id}: {$reason}",
            ];

            $response = Http::withToken($this->secretKey)
                ->post($this->baseUrl.'/refund', $payload);

            if (! $response->successful()) {
                throw new \Exception('Paystack refund API error: '.$response->body());
            }

            $data = $response->json();

            if (! $data['status']) {
                throw new \Exception('Paystack refund failed: '.$data['message']);
            }

            $payment->markAsRefunded($reason);

            Log::info('Payment refunded', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'refund_id' => $data['data']['id'],
                'reason' => $reason,
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

    protected function handleGuestAccountCreation(Booking $booking): void
    {
        try {
            if (! $booking->brand_user_id && $booking->guest_email && $booking->guest_name) {
                $guestAccountService = app(GuestAccountCreationService::class);
                $user = $guestAccountService->createAccountForGuest($booking);

                if ($user) {
                    Log::info('Guest account processed after payment', [
                        'booking_id' => $booking->id,
                        'user_id' => $user->id,
                        'guest_email' => $booking->guest_email,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle guest account creation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function verifyBankAccount(string $accountNumber, string $bankCode): array
    {
        $response = Http::withToken($this->secretKey)
            ->get($this->baseUrl.'/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ]);

        Log::info('Paystack verification request', [
            'account_number' => substr($accountNumber, 0, 4).'***'.substr($accountNumber, -4),
            'bank_code' => $bankCode,
            'secret_key_prefix' => substr($this->secretKey, 0, 7).'***',
        ]);

        if (! $response->successful()) {
            $body = $response->body();
            $message = $body;
            $json = json_decode($body, true);
            if (is_array($json) && isset($json['message'])) {
                $message = $json['message'];
            }
            Log::error('Paystack verification failed', [
                'status' => $response->status(),
                'response' => $body,
            ]);
            throw new \Exception('Bank account verification failed: '.$message);
        }

        $data = $response->json();

        if (! $data['status']) {
            Log::error('Paystack verification returned error', [
                'message' => $data['message'] ?? 'Unknown error',
                'data' => $data,
            ]);
            throw new \Exception('Invalid bank account details: '.($data['message'] ?? 'Unknown error'));
        }

        Log::info('Bank account verified successfully', [
            'account_name' => $data['data']['account_name'] ?? 'Unknown',
            'bank_id' => $data['data']['bank_id'] ?? 'Unknown',
        ]);

        return $data['data'];
    }

    protected function verifyBankAccountInternal(string $accountNumber, string $bankCode): array
    {
        return $this->verifyBankAccount($accountNumber, $bankCode);
    }

    protected function getBankName(string $code): string
    {
        try {
            $banks = $this->getSupportedBanks();
            $bank = collect($banks)->firstWhere('code', $code);

            return $bank['name'] ?? 'Unknown Bank';
        } catch (\Exception $e) {
            return 'Unknown Bank';
        }
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

        if (! $subaccountData['status']) {
            throw new \Exception('Paystack subaccount fetch failed: '.$subaccountData['message']);
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

        $payload = [
            'type' => 'nuban',
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

        if (! $data['status']) {
            throw new \Exception('Transfer recipient creation failed: '.$data['message']);
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
        return match ($currency) {
            'GHS' => 'GH',
            'ZAR' => 'ZA',
            'KES' => 'KE',
            default => 'NG',
        };
    }

    public function getSupportedBanks(string $countryCode = 'NG'): array
    {
        try {
            $countryMapping = [
                'NG' => 'nigeria',
                'GH' => 'ghana',
                'ZA' => 'south africa',
                'KE' => 'kenya',
            ];

            $country = $countryMapping[$countryCode] ?? 'nigeria';

            $response = Http::withToken($this->secretKey)
                ->get($this->baseUrl.'/bank', [
                    'country' => $country,
                ]);

            if (! $response->successful()) {
                throw new \Exception('Failed to fetch banks: '.$response->body());
            }

            $data = $response->json();

            if (! $data['status']) {
                throw new \Exception('Failed to fetch banks: '.$data['message']);
            }

            return $data['data'];
        } catch (\Exception $e) {
            Log::error('Failed to fetch supported banks', [
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
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
        $channels = [
            'NG' => ['card', 'bank', 'ussd', 'mobile_money', 'bank_transfer'],
            'GH' => ['card', 'bank', 'mobile_money'],
            'ZA' => ['card', 'bank'],
            'KE' => ['card', 'bank', 'mobile_money'],
        ];

        return $channels[$countryCode] ?? ['card', 'bank'];
    }
}
