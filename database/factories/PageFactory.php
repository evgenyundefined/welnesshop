<?php

namespace Database\Factories;

use App\Enums\PageVisibility;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::ucfirst(fake()->unique()->words(3, true));

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'title' => $title,
            'body' => "<h2>{$title}</h2><p>".fake()->paragraph().'</p>',
            'position' => fake()->numberBetween(0, 100),
            'visibility' => PageVisibility::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(['visibility' => PageVisibility::Draft]);
    }

    public function unlisted(): static
    {
        return $this->state(['visibility' => PageVisibility::Unlisted]);
    }
}
