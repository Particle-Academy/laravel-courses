<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Course;

/** @extends Factory<Course> */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'slug'              => \Illuminate\Support\Str::slug($title) . '-' . $this->faker->unique()->randomNumber(5),
            'title'             => $title,
            'description'       => $this->faker->paragraph(),
            'sort_order'        => 0,
            'is_published'      => true,
            'is_required'       => false,
            'estimated_minutes' => $this->faker->numberBetween(15, 240),
            'hours'             => null,
            'price'             => null,
            'currency'          => null,
            'highlights'        => null,
        ];
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    public function priced(float $price = 99.0, string $currency = 'USD'): static
    {
        return $this->state(fn () => ['price' => $price, 'currency' => $currency]);
    }
}
