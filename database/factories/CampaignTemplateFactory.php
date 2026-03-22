<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CampaignTemplate>
 */
class CampaignTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory()->brand(),
            'category_id' => Category::factory(),
            'name' => fake()->randomElement(['Standard Video Brief', 'Social Media Sprint Brief', 'UGC Launch Brief']),
            'deliverable_options' => [
                [
                    'deliverable_option_id' => null,
                    'min' => 0,
                    'max' => null,
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'fields' => [],
                ],
            ],
            'form_schema' => [
                'sections' => [
                    [
                        'title' => 'Campaign Details',
                        'fields' => [
                            [
                                'name' => 'pitch',
                                'type' => 'textarea',
                                'label' => 'Why work with this creator?',
                                'validation' => ['required' => true, 'min' => 50],
                            ],
                            [
                                'name' => 'goals',
                                'type' => 'select',
                                'label' => 'Campaign Goal',
                                'options' => ['Sales', 'Awareness', 'App Installs'],
                            ],
                        ],
                    ],
                ],
            ],
            'is_global' => false,
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['workspace_id' => null, 'is_global' => true]);
    }
}
