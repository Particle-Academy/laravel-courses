<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\RouteDefaultsTest;

/**
 * The shipped route middleware and what the usage guide says about it must
 * agree. The guide once said `['api', 'auth:sanctum']` while the config
 * shipped `['api']`.
 *
 * The default stays `['api']` on purpose: `auth:sanctum` would close the public
 * catalogue and `GET verify/{code}`, and needs Sanctum, which this package does
 * not require.
 */

test('the shipped route middleware is the api group alone', function () {
    $shipped = require __DIR__.'/../../config/laravel-courses.php';

    expect($shipped['routes']['middleware'])->toBe(['api']);
});

test('the usage guide states the shipped middleware and no other default', function () {
    $guide = (string) file_get_contents(__DIR__.'/../../AGENTS.md');

    expect($guide)->toContain("`['api']` middleware by default")
        ->and($guide)->not->toContain("['api', 'auth:sanctum']` middleware by default");
});
