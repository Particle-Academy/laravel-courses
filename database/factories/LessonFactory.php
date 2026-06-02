<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Lesson;

/** @extends Factory<Lesson> */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'course_id'    => Course::factory(),
            'module_id'    => null,
            'slug'         => \Illuminate\Support\Str::slug($title),
            'title'        => $title,
            'content_type' => 'text',
            'content'      => '<p>' . $this->faker->paragraph() . '</p>',
            'sort_order'   => 0,
        ];
    }

    public function video(string $url = 'https://example.com/video'): static
    {
        return $this->state(fn () => [
            'content_type' => 'video',
            'video_url'    => $url,
        ]);
    }
}
