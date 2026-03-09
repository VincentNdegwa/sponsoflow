<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSubmission;
use App\Models\Product;
use App\Models\Workspace;
use App\Models\WorkspaceRating;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->workspace = Workspace::factory()->create(['type' => 'creator']);
    $this->product = Product::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->booking = Booking::factory()->create([
        'product_id' => $this->product->id,
        'workspace_id' => $this->workspace->id,
        'status' => BookingStatus::COMPLETED,
        'guest_email' => 'brand@example.com',
        'brand_user_id' => null,
    ]);
    $this->service = app(BookingService::class);
});

test('submitRating creates a workspace rating record', function () {
    $this->service->submitRating($this->booking, 5, ['Creative', 'Fast Delivery'], 'Great work!', 'brand@example.com');

    $rating = WorkspaceRating::where('booking_id', $this->booking->id)->first();

    expect($rating)->not->toBeNull()
        ->and($rating->workspace_id)->toBe($this->workspace->id)
        ->and($rating->rating)->toBe(5)
        ->and($rating->tags)->toBe(['Creative', 'Fast Delivery'])
        ->and($rating->comment)->toBe('Great work!')
        ->and($rating->rated_by_guest_email)->toBe('brand@example.com');
});

test('submitRating associates submission when one exists', function () {
    $submission = BookingSubmission::factory()->create(['booking_id' => $this->booking->id]);

    $this->service->submitRating($this->booking, 4);

    $rating = WorkspaceRating::where('booking_id', $this->booking->id)->first();

    expect($rating->booking_submission_id)->toBe($submission->id);
});

test('submitRating stores null tags and comment when omitted', function () {
    $this->service->submitRating($this->booking, 3);

    $rating = WorkspaceRating::where('booking_id', $this->booking->id)->first();

    expect($rating->tags)->toBeNull()
        ->and($rating->comment)->toBeNull();
});

test('submitRating returns error if booking already rated', function () {
    $this->service->submitRating($this->booking, 5);
    $result = $this->service->submitRating($this->booking, 4);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('already been rated');
});
