<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

test('creator can proceed when selecting an existing brand workspace', function () {
    $creatorRole = Role::firstOrCreate([
        'name' => 'creator-owner',
    ], [
        'display_name' => 'Creator Owner',
    ]);

    $creator = User::factory()->create();
    $creatorWorkspace = Workspace::factory()->creator()->create([
        'owner_id' => $creator->id,
    ]);
    $creator->addRole($creatorRole, $creatorWorkspace);

    $brandOwner = User::factory()->create();
    $brandWorkspace = Workspace::factory()->brand()->create([
        'owner_id' => $brandOwner->id,
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_active' => true,
    ]);

    Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creator->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'brand_user_id' => $brandOwner->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::CONFIRMED,
    ]);

    $this->actingAs($creator);
    session(['current_workspace_id' => $creatorWorkspace->id]);

    Livewire::test('pages::bookings.create')
        ->set('creatorProductId', $product->id)
        ->set('creatorAmount', '250')
        ->call('nextStep')
        ->assertSet('step', 2)
        ->set('brandType', 'existing')
        ->set('existingBrandWorkspaceId', (string) $brandWorkspace->id)
        ->call('nextStep')
        ->assertHasNoErrors(['existingBrandWorkspaceId'])
        ->assertSet('step', 3);
});
