<?php

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Booking;
use App\Models\BookingInviteToken;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeCreatorContext(): array
{
    $creator = User::factory()->create();
    $creatorWorkspace = Workspace::factory()->creator()->create([
        'owner_id' => $creator->id,
    ]);

    $product = Product::factory()->create([
        'workspace_id' => $creatorWorkspace->id,
        'is_active' => true,
    ]);

    return [$creator, $creatorWorkspace, $product];
}

test('invite for existing brand does not ask guest details', function () {
    [$creator, $creatorWorkspace, $product] = makeCreatorContext();

    $brandOwner = User::factory()->create();
    $brandWorkspace = Workspace::factory()->brand()->create([
        'owner_id' => $brandOwner->id,
    ]);

    $booking = Booking::factory()->create([
        'product_id' => $product->id,
        'creator_id' => $creator->id,
        'workspace_id' => $creatorWorkspace->id,
        'brand_user_id' => $brandOwner->id,
        'brand_workspace_id' => $brandWorkspace->id,
        'guest_name' => null,
        'guest_email' => null,
        'guest_company' => null,
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::PENDING_PAYMENT,
    ]);

    $token = BookingInviteToken::generateFor($booking);

    Livewire::test('pages::bookings.invite', ['token' => $token->token])
        ->assertDontSee('Your Details');
});

test('invite for new brand prefills guest details from booking', function () {
    [$creator, $creatorWorkspace, $product] = makeCreatorContext();

    $booking = Booking::factory()->create([
        'product_id' => $product->id,
        'creator_id' => $creator->id,
        'workspace_id' => $creatorWorkspace->id,
        'brand_user_id' => null,
        'brand_workspace_id' => null,
        'guest_name' => 'Acme Buyer',
        'guest_email' => 'buyer@acme.test',
        'guest_company' => 'Acme Inc',
        'type' => BookingType::INSTANT,
        'status' => BookingStatus::PENDING_PAYMENT,
    ]);

    $token = BookingInviteToken::generateFor($booking);

    Livewire::test('pages::bookings.invite', ['token' => $token->token])
        ->assertSee('Your Details')
        ->assertSet('guestName', 'Acme Buyer')
        ->assertSet('guestEmail', 'buyer@acme.test')
        ->assertSet('guestCompany', 'Acme Inc');
});
