<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * No learner could be resolved for the request: nobody is signed in, and the
 * host has not opted into a caller-supplied id. Answered as a 401.
 *
 * Deliberately not Laravel's AuthenticationException: that one redirects a
 * non-JSON request to a `login` route, which an API-only host may not have,
 * and the redirect would itself fail as a 500. Still a RuntimeException, as
 * LearnerResolver has always thrown.
 */
final class LearnerNotAuthenticated extends HttpException
{
    public function __construct(string $message = 'Unauthenticated. Sign in to act as a learner.')
    {
        parent::__construct(401, $message);
    }
}
