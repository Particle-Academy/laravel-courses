<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the authoring + admin routes behind the host's own rule.
 *
 * Applied at the route group rather than inside each controller on purpose:
 * there are fourteen controllers, and a check that has to be remembered in
 * every new one is a check that will eventually be forgotten in one.
 */
class AuthorizeCourseAdmin
{
    public function __construct(private readonly AuthorizesCourseAdmin $authorizer)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->authorizer->allows($request), 403, 'Not authorized to administer courses.');

        return $next($request);
    }
}
