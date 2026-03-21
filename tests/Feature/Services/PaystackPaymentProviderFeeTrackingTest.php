<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\PaymentConfiguration;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Providers\PaystackPaymentProvider;
use Illuminate\Support\Facades\Http;

function createPaystackBookingContext(): array
{
    $owner = User::factory()->create();
    $creator = User::factory()->create();
    $brand = User::factory()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'country_code' => 'NG',
        'currency' => 'NGN',
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
    ]);

    $booking = Booking::factory()->create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'creator_id' => $creator->id,
        'brand_user_id' => $brand->id,
        'status' => BookingStatus::PENDING_PAYMENT,
        'amount_paid' => 403.33,
        'currency' => 'NGN',
    ]);

    $config = PaymentConfiguration::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'provider' => 'paystack',
        'provider_account_id' => 'ACCT_test_123',
        'provider_data' => [
            'recipient_code' => 'RCP_test_123',
        ],
        'is_active' => true,
        'is_verified' => true,
        'verified_at' => now(),
        'platform_fee_percentage' => 10,
    ]);

    return [$booking, $config];
}

test('it tracks paystack fee breakdown when payment is confirmed', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    [$booking] = createPaystackBookingContext();

    BookingPayment::createForBooking($booking, 'paystack', [
        'provider_reference' => 're4lyvq3s3',
        'session_id' => 'access_code_123',
        'currency' => 'NGN',
        'provider_data' => [
            'platform_fee_percentage' => 10,
        ],
    ]);

    Http::fake([
        'https://api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'id' => 4099260516,
                'status' => 'success',
                'reference' => 're4lyvq3s3',
                'amount' => 40333,
                'currency' => 'NGN',
                'fees' => 10283,
                'requested_amount' => 30050,
            ],
        ], 200),
    ]);

    app(PaystackPaymentProvider::class)->handleSuccessfulPayment('re4lyvq3s3');

    $payment = BookingPayment::query()->where('provider_reference', 're4lyvq3s3')->firstOrFail();

    expect((float) $payment->amount)->toBe(403.33)
        ->and((float) data_get($payment->amount_breakdown, 'local.platform_fee_amount'))->toBe(40.33)
        ->and((float) data_get($payment->amount_breakdown, 'local.processor_fee_amount'))->toBe(102.83)
        ->and((float) data_get($payment->amount_breakdown, 'local.escrow_amount'))->toBe(363.00)
        ->and((float) data_get($payment->amount_breakdown, 'local.creator_payout_amount'))->toBe(363.00)
        ->and((float) data_get($payment->amount_breakdown, 'usd.creator_payout_amount'))->toBeGreaterThan(0)
        ->and($payment->status)->toBe('completed')
        ->and($payment->booking->status)->toBe(BookingStatus::CONFIRMED)
        ->and(data_get($payment->amount_breakdown, 'local.requested_amount'))->toBe(300.5);
});

test('it records released creator amount when funds are transferred', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    [$booking] = createPaystackBookingContext();

    BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'paystack',
        'provider_reference' => 'release_ref_123',
        'status' => 'completed',
        'amount' => 403.33,
        'currency' => 'NGN',
        'amount_breakdown' => [
            'local' => [
                'currency' => 'NGN',
                'platform_fee_amount' => 40.33,
                'creator_payout_amount' => 363.00,
            ],
            'usd' => [
                'currency' => 'USD',
                'platform_fee_amount' => 0.04,
                'creator_payout_amount' => 0.36,
            ],
        ],
        'exchange_rate_to_usd' => 0.001,
        'paid_at' => now(),
    ]);

    Http::fake([
        'https://api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => [
                'id' => 1234,
                'transfer_code' => 'TRF_abc123',
                'status' => 'success',
            ],
        ], 200),
    ]);

    $released = app(PaystackPaymentProvider::class)->releaseFunds($booking->refresh());

    $payment = BookingPayment::query()->where('provider_reference', 'release_ref_123')->firstOrFail();

    expect($released)->toBeTrue()
        ->and((float) data_get($payment->amount_breakdown, 'local.creator_released_amount'))->toBe(363.00)
        ->and((float) data_get($payment->amount_breakdown, 'usd.creator_released_amount'))->toBe(0.36)
        ->and($payment->creator_released_at)->not->toBeNull()
        ->and(data_get($payment->provider_data, 'funds_released'))->toBeTrue()
        ->and(data_get($payment->provider_data, 'release_transfer_data.transfer_code'))->toBe('TRF_abc123');
});

test('it uses and persists platform fee percentage when creating paystack connect account', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'country_code' => 'NG',
        'currency' => 'NGN',
    ]);

    Http::fake([
        'https://api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'data' => [
                'id' => 12345,
                'subaccount_code' => 'ACCT_sub_12345',
                'business_name' => $workspace->name,
                'percentage_charge' => 10,
                'settlement_schedule' => 'manual',
            ],
        ], 200),
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                [
                    'name' => 'Demo Bank',
                    'code' => '001',
                ],
            ],
        ], 200),
    ]);

    app(PaystackPaymentProvider::class)->createConnectAccount($workspace, [
        'account_number' => '0001234567',
        'bank_code' => '001',
        'account_name' => 'Demo Creator',
    ]);

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://api.paystack.co/subaccount') {
            return false;
        }

        return data_get($request->data(), 'percentage_charge') === 10.0;
    });

    $config = PaymentConfiguration::query()->where('workspace_id', $workspace->id)->where('provider', 'paystack')->firstOrFail();

    expect((float) $config->platform_fee_percentage)->toBe(10.0);
});

test('it updates existing paystack subaccount when update connect account is called', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'country_code' => 'KE',
        'currency' => 'KES',
    ]);

    PaymentConfiguration::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'provider' => 'paystack',
        'provider_account_id' => 'ACCT_existing_123',
        'is_active' => true,
        'is_verified' => true,
        'verified_at' => now(),
        'platform_fee_percentage' => 10,
        'provider_data' => [
            'subaccount_id' => 777,
        ],
    ]);

    Http::fake([
        'https://api.paystack.co/subaccount/ACCT_existing_123' => Http::response([
            'status' => true,
            'data' => [
                'id' => 777,
                'business_name' => $workspace->name,
                'percentage_charge' => 10,
                'settlement_schedule' => 'manual',
            ],
        ], 200),
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                [
                    'name' => 'M-Pesa Settlement',
                    'code' => '035',
                ],
            ],
        ], 200),
    ]);

    app(PaystackPaymentProvider::class)->updateConnectAccount($workspace, [
        'account_number' => '0700000000',
        'bank_code' => '035',
        'account_name' => 'Creator KE',
        'payment_method' => 'mobile_money',
        'bank_type' => 'mobile_money',
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && $request->url() === 'https://api.paystack.co/subaccount/ACCT_existing_123';
    });

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://api.paystack.co/subaccount');

    $config = PaymentConfiguration::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'paystack')
        ->firstOrFail();

    expect($config->provider_account_id)->toBe('ACCT_existing_123')
        ->and(data_get($config->provider_data, 'account_name'))->toBe('Creator KE')
        ->and(data_get($config->provider_data, 'bank_type'))->toBe('mobile_money');
});

test('it rejects create connect account when a paystack subaccount already exists', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'country_code' => 'KE',
        'currency' => 'KES',
    ]);

    PaymentConfiguration::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'provider' => 'paystack',
        'provider_account_id' => 'ACCT_existing_123',
        'is_active' => true,
        'is_verified' => true,
        'verified_at' => now(),
        'platform_fee_percentage' => 10,
    ]);

    expect(function () use ($workspace) {
        app(PaystackPaymentProvider::class)->createConnectAccount($workspace, [
            'account_number' => '0700000000',
            'bank_code' => '035',
            'account_name' => 'Creator KE',
        ]);
    })->toThrow('Paystack subaccount already exists. Use update instead.');
});

test('it initializes checkout with account as fee bearer and platform charge split', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    [$booking, $config] = createPaystackBookingContext();

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'access_code' => 'access_code_123',
                'authorization_url' => 'https://checkout.paystack.com/access_code_123',
            ],
        ], 200),
    ]);

    app(PaystackPaymentProvider::class)->createCheckoutSession($booking, $config);

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://api.paystack.co/transaction/initialize') {
            return false;
        }

        return data_get($request->data(), 'bearer') === 'account'
            && data_get($request->data(), 'transaction_charge') === 4033
            && data_get($request->data(), 'subaccount') === 'ACCT_test_123';
    });
});

test('it tracks refund amounts in payment breakdown for disputes', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    [$booking] = createPaystackBookingContext();

    BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'paystack',
        'provider_reference' => 'refund_ref_123',
        'provider_transaction_id' => '5955359999',
        'status' => 'completed',
        'amount' => 100.00,
        'amount_usd' => 1.00,
        'currency' => 'NGN',
        'exchange_rate_to_usd' => 0.01,
        'amount_breakdown' => [
            'local' => [
                'currency' => 'NGN',
                'gross_amount' => 100.00,
                'platform_fee_amount' => 10.00,
                'creator_payout_amount' => 90.00,
            ],
            'usd' => [
                'currency' => 'USD',
                'gross_amount' => 1.00,
                'platform_fee_amount' => 0.10,
                'creator_payout_amount' => 0.90,
            ],
        ],
        'paid_at' => now(),
    ]);

    Http::fake([
        'https://api.paystack.co/refund' => Http::response([
            'status' => true,
            'data' => [
                'id' => 999,
                'status' => 'processed',
            ],
        ], 200),
    ]);

    $refunded = app(PaystackPaymentProvider::class)->refundPayment($booking->refresh(), 'Dispute won by brand');

    $payment = BookingPayment::query()->where('provider_reference', 'refund_ref_123')->firstOrFail();

    expect($refunded)->toBeTrue()
        ->and($payment->status)->toBe('refunded')
        ->and((float) data_get($payment->amount_breakdown, 'local.refunded_amount'))->toBe(100.00)
        ->and((float) data_get($payment->amount_breakdown, 'local.platform_fee_refunded_amount'))->toBe(10.00)
        ->and((float) data_get($payment->amount_breakdown, 'local.creator_payout_reversed_amount'))->toBe(90.00)
        ->and((float) data_get($payment->amount_breakdown, 'usd.refunded_amount'))->toBe(1.00)
        ->and(data_get($payment->provider_data, 'refund_reason'))->toBe('Dispute won by brand')
        ->and(data_get($payment->provider_data, 'refund_data.id'))->toBe(999);
});

test('it uses stored bank type when creating transfer recipient', function () {
    config()->set('services.paystack.secret_key', 'sk_test_key');

    [$booking, $config] = createPaystackBookingContext();

    $config->update([
        'provider_data' => [
            'bank_type' => 'mobile_money',
            'business_name' => 'Creator Workspace',
        ],
    ]);

    BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'paystack',
        'provider_reference' => 'recipient_type_ref_123',
        'status' => 'completed',
        'amount' => 403.33,
        'currency' => 'KES',
        'amount_breakdown' => [
            'local' => [
                'currency' => 'KES',
                'platform_fee_amount' => 40.33,
                'creator_payout_amount' => 363.00,
            ],
            'usd' => [
                'currency' => 'USD',
                'platform_fee_amount' => 0.31,
                'creator_payout_amount' => 2.80,
            ],
        ],
        'paid_at' => now(),
    ]);

    Http::fake([
        'https://api.paystack.co/subaccount/*' => Http::response([
            'status' => true,
            'data' => [
                'account_name' => 'Creator Name',
                'account_number' => '0700000000',
                'bank_id' => 231,
                'settlement_bank' => 'M-PESA',
                'currency' => 'KES',
            ],
        ], 200),
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                [
                    'id' => 231,
                    'code' => '035',
                    'name' => 'M-PESA',
                    'type' => 'nuban',
                    'active' => true,
                ],
            ],
        ], 200),
        'https://api.paystack.co/transferrecipient' => Http::response([
            'status' => true,
            'data' => [
                'recipient_code' => 'RCP_mobile_type_123',
            ],
        ], 200),
        'https://api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => [
                'id' => 1234,
                'transfer_code' => 'TRF_mobile_type_123',
                'status' => 'success',
            ],
        ], 200),
    ]);

    $released = app(PaystackPaymentProvider::class)->releaseFunds($booking->refresh());

    expect($released)->toBeTrue();

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://api.paystack.co/transferrecipient') {
            return false;
        }

        return data_get($request->data(), 'type') === 'mobile_money';
    });
});
