<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ParticleAcademy\LaravelCourses\Models\TestAttempt;

class TestAttemptFinished
{
    use Dispatchable;

    public function __construct(public readonly TestAttempt $attempt)
    {
    }
}
