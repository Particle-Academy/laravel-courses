<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Module;

/** @extends Factory<Module> */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(2);

        return [
            'course_id'  => Course::factory(),
            'slug'       => \Illuminate\Support\Str::slug($title),
            'title'      => $title,
            'sort_order' => 0,
        ];
    }
}
