<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function creatorOwner(): static
    {
        return $this->afterCreating(function ($user) {
            $workspace = Workspace::factory()->creator()->create();
            $role = Role::where('name', 'creator-owner')->first();
            $user->addRole($role, $workspace);
        });
    }

    public function creatorManager(): static
    {
        return $this->afterCreating(function ($user) {
            $workspace = Workspace::factory()->creator()->create();
            $role = Role::where('name', 'creator-manager')->first();
            $user->addRole($role, $workspace);
        });
    }

    public function brandAdmin(): static
    {
        return $this->afterCreating(function ($user) {
            $workspace = Workspace::factory()->brand()->create();
            $role = Role::where('name', 'brand-admin')->first();
            $user->addRole($role, $workspace);
        });
    }

    public function brandContributor(): static
    {
        return $this->afterCreating(function ($user) {
            $workspace = Workspace::factory()->brand()->create();
            $role = Role::where('name', 'brand-contributor')->first();
            $user->addRole($role, $workspace);
        });
    }

    public function withRole(string $roleName, Workspace $workspace = null): static
    {
        return $this->afterCreating(function ($user) use ($roleName, $workspace) {
            $role = Role::where('name', $roleName)->first();
            $workspace = $workspace ?? Workspace::factory()->create();
            $user->addRole($role, $workspace);
        });
    }
}
