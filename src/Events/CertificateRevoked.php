<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use ParticleAcademy\LaravelCourses\Models\Certificate;

class CertificateRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly Certificate $certificate,
        public readonly ?string $reason = null,
    ) {
    }
}
