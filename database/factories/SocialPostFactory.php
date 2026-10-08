<?php

namespace Database\Factories;

use App\Models\SocialPost;
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
            'status' => SocialPost::STATUS_DRAFT,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SocialPost::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SocialPost::STATUS_PUBLISHED,
            'scheduled_at' => now()->subDay(),
            'published_at' => now()->subDay(),
        ]);
    }
}
