<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'workspace_id' => Workspace::factory(),
            'creator_id' => User::factory(),
            'type' => BookingType::INQUIRY,
            'status' => BookingStatus::INQUIRY,
            'guest_name' => $this->faker->name,
            'guest_email' => $this->faker->email,
            'guest_company' => $this->faker->optional()->company,
            'amount_paid' => $this->faker->randomFloat(2, 100, 5000),
            'requirement_data' => [],
            'account_claimed' => false,
        ];
    }
}
