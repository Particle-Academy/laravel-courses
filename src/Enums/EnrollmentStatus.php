<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Enums;

enum EnrollmentStatus: string
{
    case Active    = 'active';
    case Completed = 'completed';
    case Dropped   = 'dropped';
}
