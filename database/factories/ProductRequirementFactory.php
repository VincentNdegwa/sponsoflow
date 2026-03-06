<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRequirementFactory extends Factory
{
    public function definition(): array
    {
        $requirementTypes = [
            'text' => ['Brand Message', 'Product Features', 'Call to Action'],
            'file' => ['Brand Logo', 'Product Images', 'Video Assets'],
            'url' => ['Website Link', 'Product Page', 'Social Profile'],
            'date' => ['Launch Date', 'Campaign End Date', 'Event Date'],
            'number' => ['Target Views', 'Follower Count', 'Budget Range'],
        ];

        $type = fake()->randomKey($requirementTypes);
        $name = fake()->randomElement($requirementTypes[$type]);

        return [
            'product_id' => Product::factory(),
            'name' => $name,
            'description' => fake()->sentence(),
            'type' => $type,
            'validation_rules' => $this->getValidationRules($type),
            'options' => $this->getOptions($type),
            'is_required' => fake()->boolean(80),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'name' => fake()->randomElement(['Brand Message', 'Product Features', 'Call to Action']),
            'validation_rules' => $this->getValidationRules('text'),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'name' => fake()->randomElement(['Brand Logo', 'Product Images', 'Video Assets']),
            'validation_rules' => $this->getValidationRules('file'),
        ]);
    }

    private function getValidationRules(string $type): array
    {
        return match($type) {
            'text' => [
                'min_length' => fake()->numberBetween(10, 50),
                'max_length' => fake()->numberBetween(100, 500),
            ],
            'file' => [
                'max_size' => fake()->randomElement(['2MB', '5MB', '10MB']),
                'allowed_types' => fake()->randomElements(['jpg', 'png', 'gif', 'mp4', 'avi'], fake()->numberBetween(1, 3)),
            ],
            'url' => [
                'must_be_https' => fake()->boolean(70),
            ],
            'date' => [
                'min_date' => 'today',
                'max_date' => '+6 months',
            ],
            'number' => [
                'min' => fake()->numberBetween(0, 100),
                'max' => fake()->numberBetween(1000, 100000),
            ],
            default => [],
        };
    }

    private function getOptions(string $type): ?array
    {
        return match($type) {
            'text' => fake()->randomElements([
                'Professional tone',
                'Casual tone', 
                'Humorous approach',
                'Educational focus',
                'Emotional appeal'
            ], fake()->numberBetween(2, 4)),
            'file' => null,
            'url' => null,
            'date' => null,
            'number' => null,
            default => null,
        };
    }
}
