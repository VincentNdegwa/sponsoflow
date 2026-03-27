<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

test('system admin can start impersonation', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $systemAdminRole = Role::where('name', 'system-admin')->firstOrFail();

    $admin = User::factory()->create();
    $admin->addRole($systemAdminRole);

    $user = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.impersonate', $user))
        ->assertRedirect(route('dashboard', absolute: false));

    expect(session('impersonation.user_id'))->toBe($user->id);
    expect(auth()->guard('web')->id())->toBe($user->id);
    expect(auth()->guard('admin')->id())->toBe($admin->id);
});

test('system admin can stop impersonation', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $systemAdminRole = Role::where('name', 'system-admin')->firstOrFail();

    $admin = User::factory()->create();
    $admin->addRole($systemAdminRole);

    $user = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.impersonate', $user));

    $this->post(route('admin.impersonation.stop'))
        ->assertRedirect(route('admin.users', absolute: false));

    expect(session('impersonation.user_id'))->toBeNull();
    expect(auth()->guard('web')->check())->toBeFalse();
    expect(auth()->guard('admin')->id())->toBe($admin->id);
});

test('non-admin cannot impersonate', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user, 'admin')
        ->post(route('admin.users.impersonate', $target))
        ->assertForbidden();
});

test('cannot impersonate system admin user', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $systemAdminRole = Role::where('name', 'system-admin')->firstOrFail();

    $admin = User::factory()->create();
    $admin->addRole($systemAdminRole);

    $targetAdmin = User::factory()->create();
    $targetAdmin->addRole($systemAdminRole);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.users.impersonate', $targetAdmin))
        ->assertSessionHasErrors('impersonation');
});
