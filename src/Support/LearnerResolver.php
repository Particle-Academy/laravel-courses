<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Support;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Resolves the learner's user id for the current request.
 *
 * Strategy:
 *   1. Use $request->user() (set by the host's auth middleware).
 *   2. Fall back to an explicit `user_id` request input — useful for
 *      server-to-server callers and testing. The host can disable this
 *      fallback via config('laravel-courses.allow_input_user_id').
 */
class LearnerResolver
{
    public function resolve(Request $request): int|string
    {
        $user = $request->user();
        if ($user !== null) {
            return $user->getAuthIdentifier();
        }

        if (config('laravel-courses.allow_input_user_id', true)) {
            $explicit = $request->input('user_id') ?? $request->header('X-Learner-Id');
            if ($explicit !== null && $explicit !== '') {
                return is_numeric($explicit) ? (int) $explicit : (string) $explicit;
            }
        }

        throw new RuntimeException(
            'Unable to resolve learner. Authenticate the request or supply user_id.',
        );
    }
}
