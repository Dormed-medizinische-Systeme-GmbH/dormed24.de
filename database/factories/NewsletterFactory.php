<?php

namespace Database\Factories;

use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Newsletter>
 */
class NewsletterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Newsletter '.fake()->monthName(),
            'subject' => fake()->sentence(5),
            'preheader' => fake()->sentence(8),
        ];
    }

    /**
     * Mit gespeichertem Inhalt aus dem Editor.
     */
    public function withContent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'design_json' => ['pages' => [['component' => '<mjml><mj-body></mj-body></mjml>']]],
            'mjml' => '<mjml><mj-body><mj-section><mj-column><mj-text>Hallo Praxis</mj-text></mj-column></mj-section></mj-body></mjml>',
            'html' => '<!doctype html><html><head><title></title></head><body><div><p>Hallo Praxis</p></div></body></html>',
        ]);
    }
}
