<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Stand-in for the host application's user model.
 *
 * The package never ships a user table — enrollments and certificates point at
 * whatever the host configured. Running the suite against this stand-in is what
 * keeps `laravel-courses.user_model` an actual contract rather than a comment.
 */
class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
