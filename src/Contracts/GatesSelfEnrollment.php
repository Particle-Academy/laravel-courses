<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Contracts;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

/**
 * Decides whether a learner may enrol THEMSELVES through
 * `POST enrollments`.
 *
 * The package knows a course's price but takes no payments, so it cannot tell
 * a learner who paid from one who did not. The default binding
 * (`FreeEnrollmentOnly`) therefore lets a learner self-enrol only in something
 * that costs nothing. A host that sells courses creates the enrollment itself
 * once payment confirms — through `EnrollmentService`, which this gate does not
 * govern — and needs no binding at all.
 *
 * Bind your own to change the rule, e.g. to admit members of a plan:
 *
 *   $this->app->bind(GatesSelfEnrollment::class, MyMembershipGate::class);
 *
 * Asked only for a NEW enrollment. Re-posting for something the learner is
 * already enrolled in returns the existing enrollment without asking.
 */
interface GatesSelfEnrollment
{
    public function allows(Request $request, int|string $userId, Curriculum|Course $target): bool;
}
