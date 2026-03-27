<?php

use App\Models\User;

test('stripe payment settings page renders for verified users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/payments');

    $response->assertStatus(200);
    $response->assertSee('Stripe', false);
});
