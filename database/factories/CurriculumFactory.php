<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

/** @extends Factory<Curriculum> */
class CurriculumFactory extends Factory
{
    use UsesFakeData;

    protected $model = Curriculum::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = $this->fakeData()->unique()->sentence(3);

        return [
            'slug'         => \Illuminate\Support\Str::slug($title) . '-' . $this->fakeData()->unique()->randomNumber(5),
            'title'        => $title,
            'description'  => $this->fakeData()->paragraph(),
            'sort_order'   => 0,
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
