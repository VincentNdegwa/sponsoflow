<?php

use App\Enums\SlotStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\Slot;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

it('updates selected product state without loading slots until view slots is clicked', function () {
    $role = Role::create([
        'name' => 'creator-owner',
        'display_name' => 'Creator Owner',
    ]);

    $creator = User::factory()->create([
        'is_public_profile' => true,
        'public_slug' => 'creator-preview',
    ]);

    $workspace = Workspace::factory()->create([
        'owner_id' => $creator->id,
        'type' => 'creator',
    ]);

    $creator->addRole($role, $workspace);

    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
        'is_public' => true,
        'is_active' => true,
    ]);

    Slot::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $workspace->id,
        'slot_date' => now()->addDays(5)->toDateString(),
        'status' => SlotStatus::Available,
    ]);

    $this->actingAs($creator);

    $component = Livewire::test('pages::public.creator.show', ['user' => $creator])
        ->call('selectProduct', $product->id)
        ->assertSet('selectedProductId', $product->id)
        ->assertSet('showBookingDrawer', false);

    expect($component->get('availableSlots'))->toBe([]);

    $component->call('viewSlots', $product->id);

    expect($component->get('availableSlots'))->not->toBeEmpty();
});
