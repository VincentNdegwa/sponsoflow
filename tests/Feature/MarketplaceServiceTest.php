<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\CampaignApplicationStatus;
use App\Enums\CampaignSlotStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CampaignService;
use App\Services\MarketplaceService;
use Livewire\Livewire;

test('creator can apply to a public marketplace campaign', function () {
    $brandWorkspace = Workspace::factory()->brand()->create();
    $creatorWorkspace = Workspace::factory()->creator()->create();

    $campaign = Campaign::factory()->create([
        'workspace_id' => $brandWorkspace->id,
        'status' => CampaignStatus::Published,
        'is_public' => true,
    ]);

    $product = Product::factory()->active()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_public' => true,
    ]);

    $application = app(MarketplaceService::class)->submitCreatorApplication(
        campaign: $campaign,
        creatorWorkspace: $creatorWorkspace,
        product: $product,
        pitch: 'We can deliver a high-performing Instagram reel for this launch.',
    );

    expect($application->status)->toBe(CampaignApplicationStatus::Submitted);
    expect($application->campaign_id)->toBe($campaign->id);
    expect($application->product_id)->toBe($product->id);
    expect(data_get($application->notes, 'pitch'))->toBe('We can deliver a high-performing Instagram reel for this launch.');
});

test('brand approval creates a marketplace booking and slot', function () {
    $brandWorkspace = Workspace::factory()->brand()->create();
    $creatorWorkspace = Workspace::factory()->creator()->create();

    $campaign = Campaign::factory()->create([
        'workspace_id' => $brandWorkspace->id,
        'status' => CampaignStatus::Published,
        'is_public' => true,
    ]);

    $product = Product::factory()->active()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_public' => true,
    ]);

    $application = app(CampaignService::class)->submitApplication(
        campaign: $campaign,
        creatorWorkspace: $creatorWorkspace,
        product: $product,
        notes: ['pitch' => 'Creator pitch', 'source' => 'marketplace'],
    );

    app()->instance('current.workspace', $brandWorkspace);

    $booking = app(MarketplaceService::class)->approveApplicationAndCreateBooking($application->fresh());

    expect($booking->type)->toBe(BookingType::MARKETPLACE_APPLICATION);
    expect($booking->status)->toBe(BookingStatus::PENDING);
    expect($booking->campaign_slot_id)->not->toBeNull();
    expect($booking->brand_workspace_id)->toBe($brandWorkspace->id);

    $booking->campaignSlot->refresh();
    expect($booking->campaignSlot->status)->toBe(CampaignSlotStatus::Pending);

    $application->refresh();
    expect($application->status)->toBe(CampaignApplicationStatus::Approved);
});

test('marketplace blocks applying to paused campaigns', function () {
    $creatorOwner = User::factory()->create();
    $creatorWorkspace = Workspace::factory()->creator()->create([
        'owner_id' => $creatorOwner->id,
        'currency' => 'USD',
    ]);
    $brandWorkspace = Workspace::factory()->brand()->create([
        'currency' => 'USD',
    ]);

    test()->actingAs($creatorOwner);
    app()->instance('current.workspace', $creatorWorkspace);
    session(['current_workspace_id' => $creatorWorkspace->id]);

    $campaign = Campaign::factory()->create([
        'workspace_id' => $brandWorkspace->id,
        'status' => CampaignStatus::Paused,
        'is_public' => true,
    ]);

    $product = Product::factory()->active()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_public' => true,
    ]);

    Livewire::test('pages::marketplace.index')
        ->assertSee('Applications are paused.')
        ->set('applyCampaignId', $campaign->id)
        ->set('selectedProductId', $product->id)
        ->call('submitApplication')
        ->assertSet('applyError', 'Applications are paused for this campaign right now.');
});
