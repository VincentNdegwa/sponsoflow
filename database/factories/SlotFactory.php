<?php

namespace Database\Factories;

use App\Enums\SlotStatus;
use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlotFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();
        
        return [
            'product_id' => $product->id,
            'workspace_id' => $product->workspace_id,
            'booked_by_user_id' => null,
            'slot_date' => fake()->dateTimeBetween('now', '+3 months'),
            'slot_time' => fake()->optional(0.5)->time(),
            'price' => fake()->randomFloat(2, 50, 800),
            'status' => fake()->randomElement(SlotStatus::cases()),
            'reserved_until' => null,
            'brand_assets' => null,
            'notes' => fake()->optional(0.3)->paragraph(),
            'proof_url' => null,
            'proof_submitted_at' => null,
            'completed_at' => null,
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotStatus::Available,
            'brand_assets' => null,
            'proof_url' => null,
            'proof_submitted_at' => null,
        ]);
    }

    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotStatus::Reserved,
            'reserved_until' => fake()->dateTimeBetween('+1 hour', '+24 hours'),
            'brand_assets' => null,
            'proof_url' => null,
            'proof_submitted_at' => null,
        ]);
    }

    public function booked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotStatus::Booked,
            'brand_assets' => [
                'logo_url' => fake()->imageUrl(400, 400, 'business'),
                'brand_colors' => [fake()->hexColor(), fake()->hexColor()],
                'taglines' => fake()->words(3, true),
            ],
            'notes' => fake()->paragraph(),
            'proof_url' => null,
            'proof_submitted_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotStatus::Processing,
            'brand_assets' => [
                'logo_url' => fake()->imageUrl(400, 400, 'business'),
                'brand_colors' => [fake()->hexColor(), fake()->hexColor()],
                'taglines' => fake()->words(3, true),
            ],
            'notes' => fake()->paragraph(),
            'proof_url' => fake()->imageUrl(800, 600),
            'proof_submitted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotStatus::Completed,
            'brand_assets' => [
                'logo_url' => fake()->imageUrl(400, 400, 'business'),
                'brand_colors' => [fake()->hexColor(), fake()->hexColor()],
                'taglines' => fake()->words(3, true),
            ],
            'notes' => fake()->paragraph(),
            'proof_url' => fake()->imageUrl(800, 600),
            'proof_submitted_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SlotStatus::Available,
            'notes' => fake()->randomElement([
                'Brand cancelled booking',
                'Creator unavailable',
                'Technical issues',
                'Payment failed',
                'Content guidelines violation',
            ]),
        ]);
    }

    public function forDate($date): static
    {
        return $this->state(fn (array $attributes) => [
            'slot_date' => $date,
        ]);
    }
}
