<?php

use App\Enums\BookingStatus;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Product;
use App\Models\Slot;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PaymentCompletionService;

test('it finalizes a payment and updates booking and slot status', function () {
    $creator = User::factory()->create();
    $workspace = Workspace::factory()->creator()->forOwner($creator->id)->create();
    $product = Product::factory()->state(['workspace_id' => $workspace->id])->create();
    $slot = Slot::factory()->state([
        'product_id' => $product->id,
        'workspace_id' => $workspace->id,
        'status' => SlotStatus::Reserved,
    ])->create();

    $booking = Booking::factory()->state([
        'product_id' => $product->id,
        'workspace_id' => $workspace->id,
        'creator_id' => $creator->id,
        'slot_id' => $slot->id,
        'status' => BookingStatus::PENDING_PAYMENT,
        'guest_email' => null,
        'guest_name' => null,
    ])->create();

    $payment = BookingPayment::createForBooking($booking, 'stripe', [
        'provider_reference' => 'sess_test_1',
        'session_id' => 'sess_test_1',
        'currency' => 'USD',
    ]);

    $service = app(PaymentCompletionService::class);

    $service->finalizePayment($payment, [
        'provider_transaction_id' => 'pi_test_1',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    expect($payment->refresh()->status)->toBe('completed');
    expect($booking->refresh()->status)->toBe(BookingStatus::CONFIRMED);
    expect($slot->refresh()->status)->toBe(SlotStatus::Booked);
});
