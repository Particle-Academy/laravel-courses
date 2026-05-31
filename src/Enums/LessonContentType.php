<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Enums;

enum LessonContentType: string
{
    case Text  = 'text';
    case Video = 'video';
    case Mixed = 'mixed';
}
