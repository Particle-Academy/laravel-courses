<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The course rules refused a request: a third attempt where two are allowed, a
 * resubmitted attempt, a lesson outside the enrollment, impossible hours.
 *
 * It is an HttpException, so Laravel answers it as a 4xx in the same shape as
 * the package's `abort()`s (`{"message": ...}` for JSON) rather than a 500,
 * and does not log it as an error. It is still a RuntimeException, so a host
 * calling the services directly and catching that keeps working.
 */
final class CourseRuleViolation extends HttpException
{
    /** The request clashes with the state of the thing it acts on (409). */
    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    /** The request names something the rules do not accept (422). */
    public static function unprocessable(string $message): self
    {
        return new self(422, $message);
    }
}
