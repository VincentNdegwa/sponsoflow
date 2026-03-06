<?php

use App\Models\User;
use Livewire\Livewire;

it('allows users to access public profile management page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
         ->get(route('profile.public'))
         ->assertOk();
});

it('allows users to update their public profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    
    Livewire::test('pages::profile.public')
        ->set('profileData.public_slug', 'john-doe')
        ->set('profileData.public_bio', 'I am a content creator')
        ->set('profileData.is_public_profile', true)
        ->call('saveProfile')
        ->assertDispatched('profile-updated');

    expect($user->fresh())
        ->public_slug->toBe('john-doe')
        ->public_bio->toBe('I am a content creator')
        ->is_public_profile->toBe(true);
});

it('validates slug uniqueness', function () {
    $existingUser = User::factory()->create(['public_slug' => 'taken-slug']);
    $user = User::factory()->create();

    $this->actingAs($user);
    
    Livewire::test('pages::profile.public')
        ->set('profileData.public_slug', 'taken-slug')
        ->call('saveProfile')
        ->assertHasErrors(['profileData.public_slug']);
});

it('generates slug from name', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    $this->actingAs($user);
    
    Livewire::test('pages::profile.public')
        ->call('generateSlug')
        ->assertSet('profileData.public_slug', 'john-doe');
});

it('can preview public profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    
    Livewire::test('pages::profile.public')
        ->set('profileData.public_slug', 'test-creator')
        ->call('previewProfile')
        ->assertDispatched('open-preview', route('creator.show', 'test-creator'));
});
