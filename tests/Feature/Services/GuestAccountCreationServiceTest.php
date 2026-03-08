<?php

namespace Tests\Feature\Services;

use App\Models\Booking;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ClaimAccountNotification;
use App\Services\GuestAccountCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestAccountCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::create(['name' => 'brand-admin', 'display_name' => 'Brand Admin']);
        Role::create(['name' => 'creator-owner', 'display_name' => 'Creator Owner']);
    }

    public function test_creates_account_for_guest_with_all_details(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create(['type' => 'creator']);
        $product = Product::factory()->create(['workspace_id' => $workspace->id]);
        
        $booking = Booking::factory()->create([
            'product_id' => $product->id,
            'workspace_id' => $workspace->id,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_company' => 'Acme Corp',
            'brand_user_id' => null,
            'brand_workspace_id' => null,
        ]);

        $service = app(GuestAccountCreationService::class);
        $user = $service->createAccountForGuest($booking);

        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        // Check workspace was created
        $brandWorkspace = $user->workspaces()->where('type', 'brand')->first();
        $this->assertNotNull($brandWorkspace);
        $this->assertEquals('Acme Corp', $brandWorkspace->name);
        $this->assertEquals($user->id, $brandWorkspace->owner_id);

        // Check booking was updated
        $booking->refresh();
        $this->assertEquals($user->id, $booking->brand_user_id);
        $this->assertEquals($brandWorkspace->id, $booking->brand_workspace_id);

        // Check notification was sent
        Notification::assertSentTo($user, ClaimAccountNotification::class);
    }

    public function test_creates_brand_workspace_for_existing_user_without_one(): void
    {
        $existingUser = User::factory()->create(['email' => 'john@example.com']);
        $creatorWorkspace = Workspace::factory()->create([
            'type' => 'creator',
            'owner_id' => $existingUser->id
        ]);
        
        $creatorRole = Role::where('name', 'creator-owner')->first();
        $existingUser->addRole($creatorRole, $creatorWorkspace);
        
        $workspace = Workspace::factory()->create(['type' => 'creator']);
        $product = Product::factory()->create(['workspace_id' => $workspace->id]);
        
        $booking = Booking::factory()->create([
            'product_id' => $product->id,
            'workspace_id' => $workspace->id,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_company' => 'Acme Corp',
            'brand_user_id' => null,
            'brand_workspace_id' => null,
        ]);

        $service = app(GuestAccountCreationService::class);
        $result = $service->createAccountForGuest($booking);

        $this->assertNotNull($result);
        $this->assertEquals($existingUser->id, $result->id);
        
        $brandWorkspace = $existingUser->workspaces()->where('type', 'brand')->first();
        $this->assertNotNull($brandWorkspace);
        $this->assertEquals('Acme Corp', $brandWorkspace->name);

        $booking->refresh();
        $this->assertEquals($existingUser->id, $booking->brand_user_id);
        $this->assertEquals($brandWorkspace->id, $booking->brand_workspace_id);

        $this->assertEquals(2, $existingUser->workspaces()->count());
    }

    public function test_uses_existing_brand_workspace_if_user_has_one(): void
    {
        $existingUser = User::factory()->create(['email' => 'john@example.com']);
        $brandWorkspace = Workspace::factory()->create([
            'type' => 'brand',
            'owner_id' => $existingUser->id,
            'name' => 'Existing Brand Workspace'
        ]);
        
        $brandRole = Role::where('name', 'brand-admin')->first();
        $existingUser->addRole($brandRole, $brandWorkspace);
        
        $workspace = Workspace::factory()->create(['type' => 'creator']);
        $product = Product::factory()->create(['workspace_id' => $workspace->id]);
        
        $booking = Booking::factory()->create([
            'product_id' => $product->id,
            'workspace_id' => $workspace->id,
            'guest_email' => 'john@example.com',
            'brand_user_id' => null,
        ]);

        $service = app(GuestAccountCreationService::class);
        $result = $service->createAccountForGuest($booking);

        $this->assertNotNull($result);
        $this->assertEquals($existingUser->id, $result->id);

        $booking->refresh();
        $this->assertEquals($existingUser->id, $booking->brand_user_id);
        $this->assertEquals($brandWorkspace->id, $booking->brand_workspace_id);

        $this->assertEquals(1, $existingUser->workspaces()->count());
    }

    public function test_creates_workspace_name_from_guest_name_when_no_company(): void
    {
        Notification::fake();

        $workspace = Workspace::factory()->create(['type' => 'creator']);
        $product = Product::factory()->create(['workspace_id' => $workspace->id]);
        
        $booking = Booking::factory()->create([
            'product_id' => $product->id,
            'workspace_id' => $workspace->id,
            'guest_name' => 'Jane Smith',
            'guest_email' => 'jane@example.com',
            'guest_company' => null,
            'brand_user_id' => null,
        ]);

        $service = app(GuestAccountCreationService::class);
        $user = $service->createAccountForGuest($booking);

        $brandWorkspace = $user->workspaces()->where('type', 'brand')->first();
        $this->assertEquals("Jane Smith's Brand Workspace", $brandWorkspace->name);
    }
}