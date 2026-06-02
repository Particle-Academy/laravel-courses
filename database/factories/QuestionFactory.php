<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Enums\QuestionType;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'test_id'    => Test::factory(),
            'prompt'     => $this->faker->sentence() . '?',
            'type'       => QuestionType::MultipleChoice,
            'points'     => 1,
            'sort_order' => 0,
        ];
    }

    public function trueFalse(): static
    {
        return $this->state(fn () => ['type' => QuestionType::TrueFalse]);
    }

    public function multiSelect(): static
    {
        return $this->state(fn () => ['type' => QuestionType::MultipleSelect]);
    }

    public function shortAnswer(): static
    {
        return $this->state(fn () => ['type' => QuestionType::ShortAnswer]);
    }
}
