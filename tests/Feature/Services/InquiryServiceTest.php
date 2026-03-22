<?php

use App\Enums\BookingStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Booking;
use App\Models\BookingInquiryToken;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['type' => 'creator']);
    $this->product = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'is_public' => true,
        'is_active' => true,
    ]);
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

test('createInquiry provisions a pending campaign slot for authenticated brand with default campaign mode', function () {
    $brandUser = User::factory()->create();
    $brandWorkspace = Workspace::factory()->brand()->create(['owner_id' => $brandUser->id]);

    $result = $this->service->createInquiry([
        'creator' => $this->booking->creator,
        'workspace' => $this->workspace,
        'product_id' => $this->product->id,
        'requirement_data' => [
            'campaign_name' => 'Autumn Launch',
            'main_goal' => 'awareness',
            'pitch' => 'Need authentic launch content.',
            'product_service_link' => 'https://example.com/product',
            'mandatory_mention' => '@brand',
            'budget' => 1200,
        ],
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'campaign_mode' => 'new',
    ]);

    expect($result['success'])->toBeTrue();

    $booking = Booking::query()->findOrFail($result['booking_id']);

    expect($booking->campaign_slot_id)->not->toBeNull()
        ->and($booking->campaignSlot)->not->toBeNull()
        ->and($booking->campaignSlot->status)->toBe(CampaignSlotStatus::Pending)
        ->and(data_get($booking->campaign_details, 'mode'))->toBe('new');
});

test('createInquiry can attach to existing workspace campaign for authenticated brand', function () {
    $brandUser = User::factory()->create();
    $brandWorkspace = Workspace::factory()->brand()->create(['owner_id' => $brandUser->id]);
    $existingCampaign = Campaign::factory()->create([
        'workspace_id' => $brandWorkspace->id,
        'title' => 'Nokia Launch 2026',
        'total_budget' => 500,
        'content_brief' => [
            '_form_schema' => [
                'sections' => [
                    [
                        'title' => 'Campaign Details',
                        'fields' => [
                            [
                                'name' => 'what_is_the_main_product_we_are_prom',
                                'type' => 'text',
                                'label' => 'What is the main product we are promoting?',
                            ],
                            [
                                'name' => 'what_are_the_3_must_say_benefits',
                                'type' => 'textarea',
                                'label' => 'What are the 3 "Must-Say" benefits?',
                            ],
                        ],
                    ],
                ],
            ],
            'what_is_the_main_product_we_are_prom' => 'Nokia X',
            'what_are_the_3_must_say_benefits' => 'Battery, camera, and durability.',
        ],
        'status' => CampaignStatus::Draft,
        'is_public' => true,
    ]);

    $result = $this->service->createInquiry([
        'creator' => $this->booking->creator,
        'workspace' => $this->workspace,
        'product_id' => $this->product->id,
        'requirement_data' => [],
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'campaign_mode' => 'existing',
        'campaign_id' => $existingCampaign->id,
    ]);

    expect($result['success'])->toBeTrue();

    $booking = Booking::query()->findOrFail($result['booking_id']);

    expect($booking->campaignSlot)->not->toBeNull()
        ->and($booking->campaignSlot->campaign_id)->toBe($existingCampaign->id)
        ->and((float) $booking->amount_paid)->toBe(500.0)
        ->and(data_get($booking->campaignSlot->content_brief, 'campaign_details.answers.what_is_the_main_product_we_are_prom'))->toBe('Nokia X')
        ->and(data_get($booking->campaignSlot->content_brief, 'campaign_details.answers.what_are_the_3_must_say_benefits'))->toBe('Battery, camera, and durability.')
        ->and(data_get($booking->campaign_details, 'selected_campaign_id'))->toBe($existingCampaign->id)
        ->and(data_get($booking->campaign_details, 'mode'))->toBe('existing');
});

test('createInquiry stores guest brand profile in requirement data and does not provision slot immediately', function () {
    $result = $this->service->createInquiry([
        'creator' => $this->booking->creator,
        'workspace' => $this->workspace,
        'product_id' => $this->product->id,
        'requirement_data' => [
            'campaign_name' => 'Guest Proposal',
            'main_goal' => 'content_creation',
            'pitch' => 'Guest-led collaboration request.',
            'product_service_link' => 'https://example.com/guest',
            'mandatory_mention' => '',
            'budget' => 980,
        ],
        'guest_data' => [
            'name' => 'Guest Brand',
            'email' => 'guest-brand@example.com',
            'company' => 'Guest Co',
        ],
    ]);

    expect($result['success'])->toBeTrue();

    $booking = Booking::query()->findOrFail($result['booking_id']);

    expect(data_get($booking->requirement_data, 'guest_brand_profile.email'))->toBe('guest-brand@example.com')
        ->and($booking->campaign_slot_id)->toBeNull();
});

test('rejectInquiry sets status to rejected and stores creator notes', function () {
    $result = $this->service->rejectInquiry($this->booking, 'Budget too low.');

    expect($result['success'])->toBeTrue();
    $fresh = $this->booking->fresh();
    expect($fresh->status)->toBe(BookingStatus::REJECTED)
        ->and($fresh->creator_notes)->toBe('Budget too low.');
});

test('rejectInquiry marks linked campaign slot as cancelled', function () {
    $brandUser = User::factory()->create();
    $brandWorkspace = Workspace::factory()->brand()->create(['owner_id' => $brandUser->id]);

    $creationResult = $this->service->createInquiry([
        'creator' => $this->booking->creator,
        'workspace' => $this->workspace,
        'product_id' => $this->product->id,
        'requirement_data' => [
            'campaign_name' => 'Reject Case',
            'main_goal' => 'awareness',
            'pitch' => 'Testing cancellation path.',
            'product_service_link' => 'https://example.com/reject',
            'mandatory_mention' => '',
            'budget' => 1400,
        ],
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'campaign_mode' => 'new',
    ]);

    $booking = Booking::query()->findOrFail($creationResult['booking_id']);
    expect($booking->campaignSlot)->not->toBeNull();

    $result = $this->service->rejectInquiry($booking, 'No longer a fit');

    expect($result['success'])->toBeTrue()
        ->and($booking->fresh()->status)->toBe(BookingStatus::REJECTED)
        ->and($booking->fresh()->campaignSlot->status)->toBe(CampaignSlotStatus::Cancelled);
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
