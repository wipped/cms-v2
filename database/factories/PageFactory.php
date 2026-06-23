<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'locale' => 'nl',
            'slug' => $slug,
            'full_path' => $slug,
            'title' => fake()->sentence(3),
            'sort_order' => 0,
            'is_visible' => true,
        ];
    }
}
