<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Support\DenyAllCourseAdmin;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Services\ScoringService;
use ParticleAcademy\LaravelCourses\Support\LearnerResolver;

class CoursesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-courses.php', 'laravel-courses');

        $this->app->singleton(EnrollmentService::class);
        $this->app->singleton(ProgressService::class);
        $this->app->singleton(ScoringService::class);
        $this->app->singleton(CertificateService::class);
        $this->app->singleton(LearnerResolver::class);

        // Deny-by-default. A host binds its own rule to switch authoring on;
        // until it does, the write routes are inert rather than open.
        $this->app->bindIf(AuthorizesCourseAdmin::class, DenyAllCourseAdmin::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-courses');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laravel-courses.php' => config_path('laravel-courses.php'),
            ], 'laravel-courses-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'laravel-courses-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-courses'),
            ], 'laravel-courses-views');
        }
    }

    private function registerRoutes(): void
    {
        if (! (bool) config('laravel-courses.routes.enabled', true)) {
            return;
        }

        Route::group([
            'prefix'     => (string) config('laravel-courses.routes.prefix', 'api/courses'),
            'middleware' => (array) config('laravel-courses.routes.middleware', ['api']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }
}
