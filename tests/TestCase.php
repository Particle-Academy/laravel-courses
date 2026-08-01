<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ParticleAcademy\LaravelCourses\CoursesServiceProvider;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Tests\Fixtures\TestUser;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [CoursesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Point the package at this suite's stand-in host model — exactly what
        // a real host does, which keeps the config contract under test rather
        // than assumed.
        $app['config']->set('laravel-courses.user_model', TestUser::class);
    }

    /** The host owns the users table, so the suite has to create it itself. */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
    }

    protected function learner(string $name = 'Ada'): TestUser
    {
        return TestUser::create([
            'name'  => $name,
            'email' => strtolower($name) . '@example.test',
        ]);
    }

    /**
     * A single-question true/false test attached to a course, with the correct
     * option first. Returns [$test, $correctOptionId, $wrongOptionId].
     *
     * @return array{0:CourseTest,1:int,2:int}
     */
    protected function trueFalseTest(Course $course, int $passingScore = 50): array
    {
        $test = CourseTest::factory()->create([
            'course_id'     => $course->id,
            'passing_score' => $passingScore,
            'is_final'      => true,
        ]);

        $question = Question::factory()->create([
            'test_id'    => $test->id,
            'type'       => 'true_false',
            'points'     => 1,
            'sort_order' => 0,
        ]);

        $correct = $question->options()->create([
            'label' => 'True', 'is_correct' => true, 'sort_order' => 0,
        ]);
        $wrong = $question->options()->create([
            'label' => 'False', 'is_correct' => false, 'sort_order' => 1,
        ]);

        return [$test->fresh(), (int) $correct->id, (int) $wrong->id];
    }
}
