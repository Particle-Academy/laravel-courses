<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Support;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Contracts\GatesSelfEnrollment;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

/**
 * The default enrollment gate: self-enrolment is for free things only.
 *
 * Before this existed the learner API enrolled anyone in anything, so a host
 * that put a checkout in front of a priced course could be walked around with
 * one POST. Fail closed on money, the same way the admin gate fails closed on
 * authority.
 */
final class FreeEnrollmentOnly implements GatesSelfEnrollment
{
    public function allows(Request $request, int|string $userId, Curriculum|Course $target): bool
    {
        return $target->price === null || (float) $target->price <= 0;
    }
}
