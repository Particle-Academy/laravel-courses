<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ParticleAcademy\LaravelCourses\Models\Enrollment;

class LearnerEnrolled
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment)
    {
    }
}
