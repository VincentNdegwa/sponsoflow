<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $productTypes = [
            'Instagram Story',
            'Instagram Post',
            'Instagram Reel',
            'TikTok Video',
            'YouTube Short',
            'Product Review',
            'Unboxing Video',
            'Tutorial Video',
            'Brand Mention',
            'Sponsored Post',
        ];

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->randomElement($productTypes),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['social_media', 'video_content', 'blog_post', 'podcast', 'live_stream']),
            'base_price' => fake()->randomFloat(2, 50, 500),
            'duration_minutes' => fake()->randomElement([15, 30, 60, 90, 120, 180]),
            'custom_attributes' => [
                'delivery_timeline' => fake()->randomElement(['24 hours', '48 hours', '1 week']),
                'revisions_included' => fake()->numberBetween(1, 3),
                'platforms' => fake()->randomElements(['Instagram', 'TikTok', 'YouTube', 'Twitter', 'Facebook'], fake()->numberBetween(1, 3)),
                'hashtags_required' => fake()->numberBetween(3, 10),
            ],
            'default_deliverables' => [
                [
                    'type_slug' => 'ig_reel',
                    'label' => 'Instagram Reel',
                    'qty' => 1,
                    'unit_price' => fake()->randomFloat(2, 80, 300),
                ],
                [
                    'type_slug' => 'ig_story',
                    'label' => 'Instagram Story',
                    'qty' => 2,
                    'unit_price' => fake()->randomFloat(2, 25, 120),
                ],
            ],
            'is_active' => fake()->boolean(90),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function socialMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'social_media',
            'name' => fake()->randomElement(['Instagram Story', 'Instagram Post', 'Instagram Reel', 'TikTok Video']),
            'duration_minutes' => fake()->randomElement([15, 30, 60]),
            'base_price' => fake()->randomFloat(2, 75, 250),
        ]);
    }

    public function videoContent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video_content',
            'name' => fake()->randomElement(['Product Review', 'Unboxing Video', 'Tutorial Video', 'YouTube Short']),
            'duration_minutes' => fake()->randomElement([60, 90, 120, 180]),
            'base_price' => fake()->randomFloat(2, 150, 500),
        ]);
    }
}
