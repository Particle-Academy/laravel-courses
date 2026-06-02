<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\LessonCompletion;

class LessonCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly Lesson $lesson,
        public readonly LessonCompletion $completion,
    ) {
    }
}
