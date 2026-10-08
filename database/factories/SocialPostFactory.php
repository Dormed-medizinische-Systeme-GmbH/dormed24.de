<?php

namespace Database\Factories;

use App\Models\SocialPost;
use App\Support\Social\SocialPostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Beitrag '.fake()->monthName(),
            'content' => fake()->paragraph(),
            'platforms' => ['linkedin'],
            'status' => SocialPostStatus::Draft,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SocialPostStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SocialPostStatus::Published,
            'scheduled_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);
    }
}
