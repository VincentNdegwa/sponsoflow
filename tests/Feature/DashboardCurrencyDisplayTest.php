<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

function createWorkspaceUserWithRole(string $workspaceType, string $roleName): array
{
    $role = Role::create([
        'name' => $roleName,
        'display_name' => ucfirst(str_replace('-', ' ', $roleName)),
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $user->id,
        'type' => $workspaceType,
        'currency' => 'KES',
        'country_code' => 'KE',
    ]);

    $user->addRole($role, $workspace);

    return [$user, $workspace];
}

test('brand dashboard total spent uses usd values from booking payments', function () {
    [$brandUser, $brandWorkspace] = createWorkspaceUserWithRole('brand', 'brand-admin');
    [, $creatorWorkspace] = createWorkspaceUserWithRole('creator', 'creator-owner');

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_active' => true,
        'is_public' => true,
    ]);

    $bookingOne = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creatorWorkspace->owner_id,
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::COMPLETED,
    ]);

    $bookingTwo = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creatorWorkspace->owner_id,
        'brand_user_id' => $brandUser->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::COMPLETED,
    ]);

    BookingPayment::create([
        'booking_id' => $bookingOne->id,
        'provider' => 'paystack',
        'provider_reference' => 'brand-test-pay-1',
        'status' => 'completed',
        'amount' => 1000,
        'amount_usd' => 7.50,
        'currency' => 'KES',
        'paid_at' => now(),
    ]);

    BookingPayment::create([
        'booking_id' => $bookingTwo->id,
        'provider' => 'paystack',
        'provider_reference' => 'brand-test-pay-2',
        'status' => 'completed',
        'amount' => 200,
        'amount_usd' => 10.00,
        'currency' => 'ZAR',
        'paid_at' => now(),
    ]);

    $this->actingAs($brandUser);
    session(['current_workspace_id' => $brandWorkspace->id]);

    Livewire::test('pages::dashboard')
        ->assertSee('$17.50');
});

test('creator dashboard can toggle revenue between local and usd', function () {
    [$creatorUser, $creatorWorkspace] = createWorkspaceUserWithRole('creator', 'creator-owner');

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_active' => true,
        'is_public' => true,
    ]);

    $booking = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creatorUser->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::COMPLETED,
        'currency' => 'KES',
    ]);

    BookingPayment::create([
        'booking_id' => $booking->id,
        'provider' => 'paystack',
        'provider_reference' => 'creator-test-pay-1',
        'status' => 'completed',
        'amount' => 1000,
        'amount_usd' => 8.00,
        'currency' => 'KES',
        'paid_at' => now(),
    ]);

    $this->actingAs($creatorUser);
    session(['current_workspace_id' => $creatorWorkspace->id]);

    Livewire::test('pages::dashboard')
        ->assertSee('KSh1,000.00')
        ->call('setCreatorRevenueCurrency', 'usd')
        ->assertSee('$8.00');
});
