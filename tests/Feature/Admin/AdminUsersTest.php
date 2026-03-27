<?php

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

test('admin users page requires admin login', function () {
    $this->get(route('admin.users'))
        ->assertRedirect(route('admin.login'));
});

test('admin users page forbids non-admin users', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();

    $this->actingAs($user, 'admin')
        ->get(route('admin.users'))
        ->assertForbidden();
});

test('admin users page shows a user list', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $systemAdminRole = Role::where('name', 'system-admin')->firstOrFail();

    $admin = User::factory()->create();
    $admin->addRole($systemAdminRole);

    $user = User::factory()->create([
        'name' => 'Taylor Otwell',
        'email' => 'taylor@example.com',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Taylor Otwell')
        ->assertSee('taylor@example.com');
});

test('admin user details page shows workspace overview', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $systemAdminRole = Role::where('name', 'system-admin')->firstOrFail();
    $creatorOwnerRole = Role::where('name', 'creator-owner')->firstOrFail();
    $brandAdminRole = Role::where('name', 'brand-admin')->firstOrFail();

    $admin = User::factory()->create();
    $admin->addRole($systemAdminRole);

    $owner = User::factory()->create();
    $creatorWorkspace = Workspace::factory()->creator()->forOwner($owner->id)->create([
        'name' => 'Creator Studio',
    ]);
    $brandWorkspace = Workspace::factory()->brand()->forOwner($owner->id)->create([
        'name' => 'Brand HQ',
    ]);

    $owner->addRole($creatorOwnerRole, $creatorWorkspace);
    $owner->addRole($brandAdminRole, $brandWorkspace);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.users.show', $owner))
        ->assertOk()
        ->assertSee('Creator Studio')
        ->assertSee('Brand HQ')
        ->assertSee($owner->email);
});
