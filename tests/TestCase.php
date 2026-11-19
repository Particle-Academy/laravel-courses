<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use ParticleAcademy\LaravelCourses\CoursesServiceProvider;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Tests\Fixtures\TestUser;

/**
 * The suite's Laravel host: a hand-built application on laravel/framework alone
 * (no orchestra/testbench). An empty skeleton in the temp directory, the
 * framework's default config, this package's provider, and in-memory SQLite.
 * Nothing leaves the process.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Artisan output is not mocked: that needs Mockery, which this suite does not carry. */
    public $mockConsoleOutput = false;

    public function createApplication(): Application
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-courses-tests';
        foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/cache', 'storage/logs'] as $dir) {
            if (! is_dir($base.'/'.$dir)) {
                mkdir($base.'/'.$dir, 0777, true);
            }
        }
        // An empty .env: the framework reads one, and a missing file is a warning.
        if (! is_file($base.'/.env')) {
            file_put_contents($base.'/.env', '');
        }

        $app = Application::configure(basePath: $base)
            ->withProviders([CoursesServiceProvider::class], withBootstrapProviders: false)
            ->withMiddleware(static function (): void {})
            ->withExceptions(static function (): void {})
            ->create();

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $config = $app['config'];
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
            $config->set('app.env', 'testing');
            $config->set('cache.default', 'array');
            $config->set('session.driver', 'array');
            $config->set('mail.default', 'array');
            $config->set('queue.default', 'sync');
            $config->set('logging.default', 'null');
            $config->set('logging.channels.null', ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]);
            $config->set('services.anthropic.key', '');
            $config->set('services.openai.key', '');
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ]);

            // Point the package at this suite's stand-in host model — exactly what
            // a real host does, which keeps the config contract under test rather
            // than assumed.
            $config->set('laravel-courses.user_model', TestUser::class);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defineDatabaseMigrations();
    }

    /** The host owns the users table, so the suite has to create it itself. */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            // Columns every real host user table carries. The certificate
            // template tests need them present to prove they are NOT reachable.
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->timestamps();
        });
    }

    public function learner(string $name = 'Ada'): TestUser
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
    public function trueFalseTest(Course $course, int $passingScore = 50): array
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
