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

test('creator dashboard shows financial cards based on payout lifecycle', function () {
    [$creatorUser, $creatorWorkspace] = createWorkspaceUserWithRole('creator', 'creator-owner');

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_active' => true,
        'is_public' => true,
    ]);

    $bookingOne = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creatorUser->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::CONFIRMED,
        'currency' => 'KES',
    ]);

    $bookingTwo = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creatorUser->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::COMPLETED,
        'currency' => 'KES',
    ]);

    $bookingThree = Booking::factory()->create([
        'product_id' => $product->id,
        'workspace_id' => $creatorWorkspace->id,
        'creator_id' => $creatorUser->id,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::COMPLETED,
        'currency' => 'KES',
    ]);

    BookingPayment::create([
        'booking_id' => $bookingOne->id,
        'provider' => 'paystack',
        'provider_reference' => 'creator-financial-pay-1',
        'status' => 'completed',
        'amount' => 1000,
        'amount_usd' => 8.00,
        'currency' => 'KES',
        'amount_breakdown' => [
            'local' => [
                'platform_fee_amount' => 100,
                'creator_payout_amount' => 900,
            ],
            'usd' => [
                'platform_fee_amount' => 0.80,
                'creator_payout_amount' => 7.20,
            ],
        ],
        'paid_at' => now(),
    ]);

    BookingPayment::create([
        'booking_id' => $bookingTwo->id,
        'provider' => 'paystack',
        'provider_reference' => 'creator-financial-pay-2',
        'status' => 'completed',
        'amount' => 2000,
        'amount_usd' => 16.00,
        'currency' => 'KES',
        'amount_breakdown' => [
            'local' => [
                'platform_fee_amount' => 200,
                'creator_payout_amount' => 1800,
            ],
            'usd' => [
                'platform_fee_amount' => 1.60,
                'creator_payout_amount' => 14.40,
            ],
        ],
        'paid_at' => now(),
    ]);

    BookingPayment::create([
        'booking_id' => $bookingThree->id,
        'provider' => 'paystack',
        'provider_reference' => 'creator-financial-pay-3',
        'status' => 'completed',
        'amount' => 3000,
        'amount_usd' => 24.00,
        'currency' => 'KES',
        'amount_breakdown' => [
            'local' => [
                'platform_fee_amount' => 300,
                'creator_payout_amount' => 2700,
            ],
            'usd' => [
                'platform_fee_amount' => 2.40,
                'creator_payout_amount' => 21.60,
            ],
        ],
        'creator_released_at' => now(),
        'paid_at' => now(),
    ]);

    $this->actingAs($creatorUser);
    session(['current_workspace_id' => $creatorWorkspace->id]);

    Livewire::test('pages::dashboard')
        ->assertSee('KSh6,000.00')
        ->assertSee('KSh600.00')
        ->assertSee('KSh900.00')
        ->assertSee('KSh1,800.00');
});
