<?php

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingSubmission;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\CampaignSlot;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Support\Str;

test('core models receive ulid uuids', function () {
    $brandWorkspace = Workspace::factory()->brand()->create();
    $creatorWorkspace = Workspace::factory()->creator()->create();

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_public' => true,
        'is_active' => true,
    ]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $brandWorkspace->id,
        'is_public' => true,
    ]);

    $application = CampaignApplication::create([
        'campaign_id' => $campaign->id,
        'creator_workspace_id' => $creatorWorkspace->id,
        'product_id' => $product->id,
    ]);

    $slot = CampaignSlot::create([
        'campaign_id' => $campaign->id,
        'creator_workspace_id' => $creatorWorkspace->id,
        'product_id' => $product->id,
    ]);

    $booking = Booking::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'product_id' => $product->id,
        'creator_id' => $creatorWorkspace->owner_id,
        'currency' => $creatorWorkspace->currency ?? 'USD',
    ]);

    $payment = BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'stripe',
        'provider_reference' => (string) Str::ulid(),
        'status' => 'pending',
        'amount' => 100,
        'currency' => 'USD',
    ]);

    $submission = BookingSubmission::factory()->create([
        'booking_id' => $booking->id,
    ]);

    expect($campaign->uuid)->toBeString()->not->toBeEmpty()
        ->and($product->uuid)->toBeString()->not->toBeEmpty()
        ->and($booking->uuid)->toBeString()->not->toBeEmpty()
        ->and($payment->uuid)->toBeString()->not->toBeEmpty()
        ->and($submission->uuid)->toBeString()->not->toBeEmpty()
        ->and($application->uuid)->toBeString()->not->toBeEmpty()
        ->and($slot->uuid)->toBeString()->not->toBeEmpty();
});
