<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Support;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;

/**
 * The default authorizer: nobody is an admin.
 *
 * A package that guesses at authorization gets it wrong in the direction that
 * costs something. Denying by default means a host that has not yet bound its
 * own rule finds the authoring API switched off — annoying, and recoverable in
 * one binding — rather than finding that anyone on the internet could mint a
 * certificate, which is neither.
 */
final class DenyAllCourseAdmin implements AuthorizesCourseAdmin
{
    public function allows(Request $request): bool
    {
        return false;
    }
}
