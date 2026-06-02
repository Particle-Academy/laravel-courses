<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

/** @extends Factory<Curriculum> */
class CurriculumFactory extends Factory
{
    protected $model = Curriculum::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'slug'         => \Illuminate\Support\Str::slug($title) . '-' . $this->faker->unique()->randomNumber(5),
            'title'        => $title,
            'description'  => $this->faker->paragraph(),
            'sort_order'   => 0,
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
