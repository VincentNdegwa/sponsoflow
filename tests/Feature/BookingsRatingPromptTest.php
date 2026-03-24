<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\BookingSubmission;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeProcessableBookingForBrandReview(): array
{
    $creator = User::factory()->create();
    $brandUser = User::factory()->create();

    $creatorWorkspace = Workspace::factory()->creator()->create([
        'owner_id' => $creator->id,
    ]);

    $brandWorkspace = Workspace::factory()->brand()->create([
        'owner_id' => $brandUser->id,
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
    ]);

    $booking = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creator->id,
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::PROCESSING,
        'guest_email' => null,
        'guest_name' => null,
        'guest_company' => null,
    ]);

    BookingSubmission::factory()->create([
        'booking_id' => $booking->id,
    ]);

    return [$brandUser, $brandWorkspace, $booking];
}

test('booking details prompts for rating after approving work and stores rating', function () {
    [$brandUser, $brandWorkspace, $booking] = makeProcessableBookingForBrandReview();

    $this->actingAs($brandUser);
    session(['current_workspace_id' => $brandWorkspace->id]);

    Livewire::test('pages::bookings.show', ['booking' => $booking])
        ->call('approveWork')
        ->assertSet('showRatingPrompt', true)
        ->call('setRating', 5)
        ->set('ratingComment', 'Excellent collaboration')
        ->call('submitRating')
        ->assertSet('showRatingPrompt', false);

    $this->assertDatabaseHas('workspace_ratings', [
        'booking_id' => $booking->id,
        'rating' => 5,
        'comment' => 'Excellent collaboration',
        'rated_by_user_id' => $brandUser->id,
    ]);
});

test('bookings index prompts for rating after approving work and stores rating', function () {
    [$brandUser, $brandWorkspace, $booking] = makeProcessableBookingForBrandReview();

    $this->actingAs($brandUser);
    session(['current_workspace_id' => $brandWorkspace->id]);

    Livewire::test('pages::bookings.index')
        ->call('confirmApproveWork', $booking->uuid)
        ->call('approveWork')
        ->assertSet('showRatingPrompt', true)
        ->call('setRating', 4)
        ->set('ratingComment', 'Great delivery speed')
        ->call('submitRating')
        ->assertSet('showRatingPrompt', false);

    $this->assertDatabaseHas('workspace_ratings', [
        'booking_id' => $booking->id,
        'rating' => 4,
        'comment' => 'Great delivery speed',
        'rated_by_user_id' => $brandUser->id,
    ]);
});
