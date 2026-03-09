<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookingSubmission>
 */
class BookingSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => \App\Models\Booking::factory(),
            'work_url' => $this->faker->optional()->url(),
            'screenshot_path' => null,
            'revision_notes' => null,
            'revision_number' => 0,
            'auto_approve_at' => now()->addHours(72),
        ];
    }
}
