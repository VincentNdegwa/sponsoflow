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
        ->and((float) $payment->platform_fee_amount)->toBe(40.33)
        ->and((float) $payment->processor_fee_amount)->toBe(102.83)
        ->and((float) $payment->escrow_amount)->toBe(363.00)
        ->and((float) $payment->creator_payout_amount)->toBe(363.00)
        ->and($payment->status)->toBe('completed')
        ->and($payment->booking->status)->toBe(BookingStatus::CONFIRMED)
        ->and(data_get($payment->provider_data, 'payment_breakdown.requested_amount'))->toBe(300.5);
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
        'platform_fee_amount' => 40.33,
        'creator_payout_amount' => 363.00,
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
        ->and((float) $payment->creator_released_amount)->toBe(363.00)
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
