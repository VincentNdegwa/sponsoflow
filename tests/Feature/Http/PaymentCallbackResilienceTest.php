<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PaymentService;

use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

function createCompletedPaystackPayment(string $reference = 're4lyvq3s3'): BookingPayment
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
        'status' => BookingStatus::CONFIRMED,
        'amount_paid' => 403.33,
        'currency' => 'NGN',
    ]);

    return BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'paystack',
        'provider_reference' => $reference,
        'status' => 'completed',
        'amount' => 403.33,
        'currency' => 'NGN',
        'paid_at' => now(),
    ]);
}

test('paystack callback redirects to success when payment is already completed even if handler throws', function () {
    createCompletedPaystackPayment('re4lyvq3s3');

    $mock = \Mockery::mock(PaymentService::class);
    $mock->shouldReceive('handleSuccessfulPayment')
        ->once()
        ->with('re4lyvq3s3', 'paystack')
        ->andThrow(new Exception('SQLSTATE[HY000]: no such table: jobs'));
    app()->instance(PaymentService::class, $mock);

    $response = get(route('payment.paystack.callback', ['reference' => 're4lyvq3s3']));

    $response->assertRedirect(route('payment.success', ['reference' => 're4lyvq3s3']));
    $response->assertSessionHas('message', 'Payment successful!');
});

test('payment success page renders when callback flash message exists without session id', function () {
    $response = withSession(['message' => 'Payment successful!'])
        ->get(route('payment.success'));

    $response->assertOk();
    $response->assertViewIs('payment.success');
});

test('payment success page shows claim account cta for unclaimed guest booking', function () {
    $payment = createCompletedPaystackPayment('claimable-ref');

    $claimUser = User::factory()->create([
        'email' => 'guest-claim@example.com',
    ]);

    $payment->booking->update([
        'guest_name' => 'Guest Claim',
        'guest_email' => 'guest-claim@example.com',
        'brand_user_id' => $claimUser->id,
        'account_claimed' => false,
    ]);

    $response = get(route('payment.success', ['reference' => 'claimable-ref']));

    $response->assertOk();
    $response->assertViewIs('payment.success');
    $response->assertSee('Claim Account');
});
