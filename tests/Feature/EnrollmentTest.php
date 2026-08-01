<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

use Illuminate\Support\Facades\Event;
use ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus;
use ParticleAcademy\LaravelCourses\Events\EnrollmentCompleted;
use ParticleAcademy\LaravelCourses\Events\LearnerEnrolled;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;

class EnrollmentTest extends TestCase
{
    private function service(): EnrollmentService
    {
        return $this->app->make(EnrollmentService::class);
    }

    public function test_it_enrolls_a_learner_in_a_curriculum(): void
    {
        $user = $this->learner();
        $curriculum = Curriculum::factory()->create();

        $enrollment = $this->service()->enroll($user->id, $curriculum);

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(Curriculum::class, $enrollment->enrollable_type);
        $this->assertSame($curriculum->id, $enrollment->enrollable_id);
        $this->assertNotNull($enrollment->started_at);
    }

    public function test_it_enrolls_a_learner_in_a_bare_course(): void
    {
        $user = $this->learner();
        $course = Course::factory()->create();

        $enrollment = $this->service()->enroll($user->id, $course);

        $this->assertSame(Course::class, $enrollment->enrollable_type);
        $this->assertSame($course->id, $enrollment->enrollable_id);
    }

    public function test_enrolling_twice_returns_the_same_enrollment(): void
    {
        $user = $this->learner();
        $curriculum = Curriculum::factory()->create();

        $first  = $this->service()->enroll($user->id, $curriculum);
        $second = $this->service()->enroll($user->id, $curriculum);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_it_dispatches_LearnerEnrolled_only_on_first_enrollment(): void
    {
        Event::fake([LearnerEnrolled::class]);

        $user = $this->learner();
        $curriculum = Curriculum::factory()->create();

        $this->service()->enroll($user->id, $curriculum);
        $this->service()->enroll($user->id, $curriculum);

        // Re-enrolling is idempotent, so a host listening for the event to send
        // a welcome email does not send a second one.
        Event::assertDispatchedTimes(LearnerEnrolled::class, 1);
    }

    public function test_the_same_learner_can_enrol_in_a_curriculum_and_a_course_independently(): void
    {
        $user = $this->learner();
        $curriculum = Curriculum::factory()->create();
        $course = Course::factory()->create();

        $a = $this->service()->enroll($user->id, $curriculum);
        $b = $this->service()->enroll($user->id, $course);

        $this->assertNotSame($a->id, $b->id);
        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_completing_an_enrollment_sets_status_and_dispatches_once(): void
    {
        Event::fake([EnrollmentCompleted::class]);

        $user = $this->learner();
        $enrollment = $this->service()->enroll($user->id, Curriculum::factory()->create());

        $completed = $this->service()->complete($enrollment);
        $this->assertSame(EnrollmentStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);

        // Completing an already-complete enrollment is a no-op, not a second
        // event — otherwise a retried job re-issues downstream side effects.
        $this->service()->complete($completed);
        Event::assertDispatchedTimes(EnrollmentCompleted::class, 1);
    }

    public function test_dropping_an_enrollment_sets_dropped_status(): void
    {
        $user = $this->learner();
        $enrollment = $this->service()->enroll($user->id, Curriculum::factory()->create());

        $dropped = $this->service()->drop($enrollment);

        $this->assertSame(EnrollmentStatus::Dropped, $dropped->status);
    }

    public function test_userFor_resolves_the_host_configured_model(): void
    {
        $user = $this->learner('Grace');
        $enrollment = $this->service()->enroll($user->id, Curriculum::factory()->create());

        $resolved = $this->service()->userFor($enrollment);

        $this->assertNotNull($resolved);
        $this->assertSame('Grace', $resolved->name);
    }

    public function test_userFor_throws_when_the_host_model_is_misconfigured(): void
    {
        $user = $this->learner();
        $enrollment = $this->service()->enroll($user->id, Curriculum::factory()->create());

        config()->set('laravel-courses.user_model', 'App\\Models\\NotReal');

        $this->expectException(\RuntimeException::class);
        $this->service()->userFor($enrollment);
    }

    public function test_an_enrollment_reports_expiry(): void
    {
        $user = $this->learner();

        $live = $this->service()->enroll(
            $user->id,
            Curriculum::factory()->create(),
            expiresAt: now()->addDay()->toDateTimeImmutable(),
        );
        $this->assertFalse($live->isExpired());

        $stale = $this->service()->enroll(
            $user->id,
            Course::factory()->create(),
            expiresAt: now()->subDay()->toDateTimeImmutable(),
        );
        $this->assertTrue($stale->isExpired());
    }
}
