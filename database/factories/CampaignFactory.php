<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\CampaignTemplate;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deliverables = [
            [
                'id' => 'row_1',
                'deliverable_option_id' => null,
                'type_slug' => 'ig_reel',
                'label' => 'Instagram Reel',
                'qty' => 1,
                'unit_price' => 5000,
                'subtotal' => 5000,
                'status' => 'pending',
                'proof_url' => null,
            ],
        ];

        return [
            'workspace_id' => Workspace::factory()->brand(),
            'template_id' => CampaignTemplate::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(12),
            'total_budget' => 5000,
            'content_brief' => [
                'pitch' => fake()->sentence(12),
                'goals' => fake()->randomElement(['Sales', 'Awareness', 'App Installs']),
            ],
            'deliverables' => $deliverables,
            'status' => CampaignStatus::Draft,
            'is_public' => false,
            'posted_at' => now(),
        ];
    }
}
