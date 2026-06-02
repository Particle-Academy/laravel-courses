<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\QuestionOption;

/** @extends Factory<QuestionOption> */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'label'       => $this->faker->word(),
            'is_correct'  => false,
            'sort_order'  => 0,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }
}
