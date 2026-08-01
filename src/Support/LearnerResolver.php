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
 *   2. ONLY IF the host explicitly opts in via
 *      `laravel-courses.allow_input_user_id`, fall back to a `user_id` input
 *      or `X-Learner-Id` header — useful for trusted server-to-server callers.
 *
 * That fallback used to default to ON, which made every ownership check in
 * this package decorative: the controllers do compare the enrollment's
 * user_id against the resolved learner, but an anonymous caller could simply
 * claim to BE that learner and pass. It now defaults to OFF, so an
 * unauthenticated request fails closed.
 *
 * Turning it on trusts the caller completely. Only do so behind middleware
 * that has already established who the caller is.
 */
class LearnerResolver
{
    public function resolve(Request $request): int|string
    {
        $user = $request->user();
        if ($user !== null) {
            return $user->getAuthIdentifier();
        }

        if (config('laravel-courses.allow_input_user_id', false)) {
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
