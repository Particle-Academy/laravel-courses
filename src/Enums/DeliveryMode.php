<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Enums;

enum DeliveryMode: string
{
    case Online   = 'online';
    case InPerson = 'in_person';
    case Hybrid   = 'hybrid';

    public function isOnline(): bool
    {
        return $this === self::Online || $this === self::Hybrid;
    }

    public function isInPerson(): bool
    {
        return $this === self::InPerson || $this === self::Hybrid;
    }
}
