<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Test;

/** @extends Factory<Test> */
class TestFactory extends Factory
{
    protected $model = Test::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'slug'                => \Illuminate\Support\Str::slug($title) . '-' . $this->faker->unique()->randomNumber(5),
            'title'               => $title,
            'description'         => $this->faker->sentence(),
            'course_id'           => Course::factory(),
            'passing_score'       => 70,
            'is_final'            => false,
            'randomize_questions' => false,
        ];
    }

    public function final(): static
    {
        return $this->state(fn () => ['is_final' => true]);
    }
}
