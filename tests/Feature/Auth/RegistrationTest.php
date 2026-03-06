<?php

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com', 
        'password' => 'password',
        'password_confirmation' => 'password',
        'workspace_type' => 'creator',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    
    $user = auth()->user();
    expect($user->workspaces)->toHaveCount(1);
    expect($user->workspaces->first()->type)->toBe('creator');
    expect($user->workspaces->first()->name)->toBe('John Doe\'s Creator Workspace');
    expect($user->hasRole('creator-owner', $user->workspaces->first()))->toBeTrue();
});

test('new users can register as brand', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    
    $response = $this->post(route('register.store'), [
        'name' => 'Jane Smith',
        'email' => 'brand@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'workspace_type' => 'brand',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    
    $user = auth()->user();
    expect($user->workspaces)->toHaveCount(1);
    expect($user->workspaces->first()->type)->toBe('brand');
    expect($user->workspaces->first()->name)->toBe('Jane Smith\'s Brand Workspace');
    expect($user->hasRole('brand-admin', $user->workspaces->first()))->toBeTrue();
});