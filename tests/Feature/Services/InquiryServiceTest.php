<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingInquiryToken;
use App\Models\Product;
use App\Models\Workspace;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['type' => 'creator']);
    $this->product = Product::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->booking = Booking::factory()->create([
        'product_id' => $this->product->id,
        'workspace_id' => $this->workspace->id,
        'status' => BookingStatus::INQUIRY,
        'guest_email' => 'brand@example.com',
        'guest_name' => 'Brand Co',
        'amount_paid' => 300.00,
    ]);
    $this->service = app(BookingService::class);
    Notification::fake();
});

test('approveInquiry sets status to pending payment and generates a respond token', function () {
    $result = $this->service->approveInquiry($this->booking);

    expect($result['success'])->toBeTrue();
    expect($this->booking->fresh()->status)->toBe(BookingStatus::PENDING_PAYMENT);

    $token = BookingInquiryToken::where('booking_id', $this->booking->id)
        ->where('purpose', 'respond')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->email)->toBe('brand@example.com')
        ->and($token->isValid())->toBeTrue();
});

test('approveInquiry sends InquiryApprovedNotification to the brand email', function () {
    $this->service->approveInquiry($this->booking);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        \App\Notifications\InquiryApprovedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'brand@example.com',
    );
});

test('approveInquiry returns error if booking is not in inquiry status', function () {
    $this->booking->update(['status' => BookingStatus::CONFIRMED]);

    $result = $this->service->approveInquiry($this->booking);

    expect($result['success'])->toBeFalse();
    Notification::assertNothingSent();
});

test('rejectInquiry sets status to rejected and stores creator notes', function () {
    $result = $this->service->rejectInquiry($this->booking, 'Budget too low.');

    expect($result['success'])->toBeTrue();
    $fresh = $this->booking->fresh();
    expect($fresh->status)->toBe(BookingStatus::REJECTED)
        ->and($fresh->creator_notes)->toBe('Budget too low.');
});

test('rejectInquiry sends InquiryRejectedNotification to the brand email', function () {
    $this->service->rejectInquiry($this->booking);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        \App\Notifications\InquiryRejectedNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'brand@example.com',
    );
});

test('rejectInquiry also works when booking is counter_offered', function () {
    $this->booking->update(['status' => BookingStatus::COUNTER_OFFERED]);

    $result = $this->service->rejectInquiry($this->booking);

    expect($result['success'])->toBeTrue()
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::REJECTED);
});

test('counterInquiry sets status to counter_offered and stores counter amount', function () {
    $result = $this->service->counterInquiry($this->booking, 450.00, 'Our standard rate.');

    expect($result['success'])->toBeTrue();
    $fresh = $this->booking->fresh();
    expect($fresh->status)->toBe(BookingStatus::COUNTER_OFFERED)
        ->and((float) $fresh->counter_amount)->toBe(450.00)
        ->and($fresh->creator_notes)->toBe('Our standard rate.');
});

test('counterInquiry generates an accept_counter token', function () {
    $this->service->counterInquiry($this->booking, 450.00);

    $token = BookingInquiryToken::where('booking_id', $this->booking->id)
        ->where('purpose', 'accept_counter')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->isValid())->toBeTrue();
});

test('counterInquiry sends InquiryCounteredNotification to the brand email', function () {
    $this->service->counterInquiry($this->booking, 450.00);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        \App\Notifications\InquiryCounteredNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'brand@example.com',
    );
});

test('fulfillInquiryBooking returns error when status does not allow fulfillment', function () {
    $this->booking->update(['status' => BookingStatus::PENDING]);

    $result = $this->service->fulfillInquiryBooking($this->booking, []);

    expect($result['success'])->toBeFalse();
});

test('fulfillInquiryBooking updates requirement_data and changes status to pending_payment', function () {
    $this->booking->update(['status' => BookingStatus::PENDING_PAYMENT]);

    // Bypass actual payment: swap payment service to avoid real Stripe call
    $mockPayment = Mockery::mock(\App\Services\PaymentService::class);
    $mockPayment->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn(['id' => 'cs_test_123', 'url' => 'https://stripe.com/pay/cs_test_123']);
    app()->instance(\App\Services\PaymentService::class, $mockPayment);

    $service = app(BookingService::class);
    $result = $service->fulfillInquiryBooking($this->booking, ['campaign_name' => 'Summer Campaign']);

    expect($result['success'])->toBeTrue()
        ->and($result['checkout_url'])->toBe('https://stripe.com/pay/cs_test_123');

    $fresh = $this->booking->fresh();
    expect($fresh->status)->toBe(BookingStatus::PENDING_PAYMENT)
        ->and($fresh->requirement_data['campaign_name'])->toBe('Summer Campaign');
});

test('pending payment inquiry without submission can proceed to payment but cannot be approved as submitted work', function () {
    $this->booking->update([
        'status' => BookingStatus::PENDING_PAYMENT,
    ]);

    $fresh = $this->booking->fresh();

    expect($fresh->canProceedInquiryPayment())->toBeTrue()
        ->and($fresh->canApprove())->toBeFalse()
        ->and($fresh->canReviewSubmittedWork())->toBeFalse();
});

test('fulfillInquiryBooking accepting counter updates amount_paid to counter_amount', function () {
    $this->booking->update([
        'status' => BookingStatus::COUNTER_OFFERED,
        'counter_amount' => 450.00,
    ]);

    $mockPayment = Mockery::mock(\App\Services\PaymentService::class);
    $mockPayment->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn(['id' => 'cs_test_456', 'url' => 'https://stripe.com/pay/cs_test_456']);
    app()->instance(\App\Services\PaymentService::class, $mockPayment);

    $service = app(BookingService::class);
    $result = $service->fulfillInquiryBooking($this->booking, [], true);

    expect($result['success'])->toBeTrue();
    expect((float) $this->booking->fresh()->amount_paid)->toBe(450.00);
});

test('fulfillInquiryBooking preserves existing requirement_data when incoming payload is empty', function () {
    $this->booking->update([
        'status' => BookingStatus::PENDING_PAYMENT,
        'requirement_data' => [
            'pitch' => 'Launch a creator-led teaser campaign.',
            'campaign_goals' => 'Boost pre-orders by 20%.',
        ],
    ]);

    $mockPayment = Mockery::mock(\App\Services\PaymentService::class);
    $mockPayment->shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn(['id' => 'cs_test_789', 'url' => 'https://stripe.com/pay/cs_test_789']);
    app()->instance(\App\Services\PaymentService::class, $mockPayment);

    $service = app(BookingService::class);
    $result = $service->fulfillInquiryBooking($this->booking, []);

    expect($result['success'])->toBeTrue();

    $fresh = $this->booking->fresh();
    expect($fresh->requirement_data['pitch'])->toBe('Launch a creator-led teaser campaign.')
        ->and($fresh->requirement_data['campaign_goals'])->toBe('Boost pre-orders by 20%.');
});
