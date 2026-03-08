<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();
        
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->randomNumber(4),
            'owner_id' => UserFactory::create()->id,
            'type' => 'creator',
            'description' => fake()->sentence(),
        ];
    }

    public function forOwner($ownerId): static
    {
        return $this->state(fn (array $attributes) => [
            'owner_id' => $ownerId,
        ]);
    }

    public function creator(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'creator',
            'slug' => Str::slug($attributes['name'] ?? fake()->firstName()) . '-content',
        ]);
    }

    public function brand(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'brand',
            'slug' => Str::slug($attributes['name'] ?? fake()->company()) . '-brand',
        ]);
    }
}
