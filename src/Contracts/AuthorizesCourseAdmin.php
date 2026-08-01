<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Contracts;

use Illuminate\Http\Request;

/**
 * Decides whether the current request may author content or act as an admin.
 *
 * This package ships no roles table and has no idea who an administrator is in
 * the host application — so rather than trust whoever mounted the routes, it
 * asks. The default binding denies everything, which makes an unbound install
 * inert instead of wide open.
 *
 * It governs the routes that create, change or destroy content (curriculums,
 * courses, modules, lessons, tests, questions, certificate templates) and the
 * ones that issue or revoke a certificate outside the normal learner flow.
 *
 * Bind your own in a service provider:
 *
 *   $this->app->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
 *       public function allows(Request $request): bool
 *       {
 *           return $request->user()?->isInstructor() ?? false;
 *       }
 *   });
 *
 * Read routes are NOT gated here. A catalogue of published courses is normally
 * public, and gating it by default would break the common case; hosts that need
 * private content should mount the package behind their own middleware via
 * `laravel-courses.routes.middleware`.
 */
interface AuthorizesCourseAdmin
{
    public function allows(Request $request): bool;
}
