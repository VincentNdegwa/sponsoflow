<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingSubmission;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('booking show page renders componentized detail sections', function () {
    $creator = User::factory()->create();
    $brandUser = User::factory()->create();

    $creatorWorkspace = Workspace::factory()->creator()->create([
        'owner_id' => $creator->id,
        'currency' => 'KES',
    ]);

    $brandWorkspace = Workspace::factory()->brand()->create([
        'owner_id' => $brandUser->id,
        'currency' => 'USD',
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'name' => 'Creator Package',
        'base_price' => 500,
        'is_active' => true,
    ]);

    $booking = Booking::factory()->create([
        'product_id' => $product->id,
        'creator_id' => $creator->id,
        'workspace_id' => $creatorWorkspace->id,
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::PROCESSING,
        'currency' => 'KES',
    ]);

    $submission = BookingSubmission::factory()->create([
        'booking_id' => $booking->id,
        'revision_number' => 0,
    ]);

    BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'paystack',
        'provider_reference' => 'ref_1234',
        'status' => 'completed',
        'amount' => 500,
        'amount_usd' => 3.75,
        'currency' => 'KES',
        'amount_breakdown' => [
            'local' => [
                'creator_payout_amount' => 450,
            ],
            'usd' => [
                'creator_payout_amount' => 3.38,
            ],
        ],
        'paid_at' => now(),
    ]);

    WorkspaceRating::create([
        'workspace_id' => $creatorWorkspace->id,
        'booking_id' => $booking->id,
        'booking_submission_id' => $submission->id,
        'rating' => 5,
        'tags' => ['Professional'],
        'comment' => 'Great quality',
        'rated_by_user_id' => $brandUser->id,
    ]);

    $this->actingAs($brandUser);
    session(['current_workspace_id' => $brandWorkspace->id]);

    Livewire::test('pages::bookings.show', ['booking' => $booking])
        ->assertSee('Product Details')
        ->assertSee('Slot Details')
        ->assertSee('Payment History')
        ->assertSee('Submission History')
        ->assertSee('Rating')
        ->assertSee('Creator Package')
        ->assertSee('Great quality');
});
