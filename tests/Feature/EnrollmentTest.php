<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\EnrollmentTest;

use Illuminate\Support\Facades\Event;
use ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus;
use ParticleAcademy\LaravelCourses\Events\EnrollmentCompleted;
use ParticleAcademy\LaravelCourses\Events\LearnerEnrolled;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;

function service(): EnrollmentService {
    return app()->make(EnrollmentService::class);
}

test('it enrolls a learner in a curriculum', function () {
    $user = $this->learner();
    $curriculum = Curriculum::factory()->create();

    $enrollment = service()->enroll($user->id, $curriculum);

    $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    $this->assertSame(Curriculum::class, $enrollment->enrollable_type);
    $this->assertSame($curriculum->id, $enrollment->enrollable_id);
    $this->assertNotNull($enrollment->started_at);
});

test('it enrolls a learner in a bare course', function () {
    $user = $this->learner();
    $course = Course::factory()->create();

    $enrollment = service()->enroll($user->id, $course);

    $this->assertSame(Course::class, $enrollment->enrollable_type);
    $this->assertSame($course->id, $enrollment->enrollable_id);
});

test('enrolling twice returns the same enrollment', function () {
    $user = $this->learner();
    $curriculum = Curriculum::factory()->create();

    $first  = service()->enroll($user->id, $curriculum);
    $second = service()->enroll($user->id, $curriculum);

    $this->assertSame($first->id, $second->id);
    $this->assertDatabaseCount('enrollments', 1);
});

test('it dispatches LearnerEnrolled only on first enrollment', function () {
    Event::fake([LearnerEnrolled::class]);

    $user = $this->learner();
    $curriculum = Curriculum::factory()->create();

    service()->enroll($user->id, $curriculum);
    service()->enroll($user->id, $curriculum);

    // Re-enrolling is idempotent, so a host listening for the event to send
    // a welcome email does not send a second one.
    Event::assertDispatchedTimes(LearnerEnrolled::class, 1);
});

test('the same learner can enrol in a curriculum and a course independently', function () {
    $user = $this->learner();
    $curriculum = Curriculum::factory()->create();
    $course = Course::factory()->create();

    $a = service()->enroll($user->id, $curriculum);
    $b = service()->enroll($user->id, $course);

    $this->assertNotSame($a->id, $b->id);
    $this->assertDatabaseCount('enrollments', 2);
});

test('completing an enrollment sets status and dispatches once', function () {
    Event::fake([EnrollmentCompleted::class]);

    $user = $this->learner();
    $enrollment = service()->enroll($user->id, Curriculum::factory()->create());

    $completed = service()->complete($enrollment);
    $this->assertSame(EnrollmentStatus::Completed, $completed->status);
    $this->assertNotNull($completed->completed_at);

    // Completing an already-complete enrollment is a no-op, not a second
    // event — otherwise a retried job re-issues downstream side effects.
    service()->complete($completed);
    Event::assertDispatchedTimes(EnrollmentCompleted::class, 1);
});

test('dropping an enrollment sets dropped status', function () {
    $user = $this->learner();
    $enrollment = service()->enroll($user->id, Curriculum::factory()->create());

    $dropped = service()->drop($enrollment);

    $this->assertSame(EnrollmentStatus::Dropped, $dropped->status);
});

test('userFor resolves the host configured model', function () {
    $user = $this->learner('Grace');
    $enrollment = service()->enroll($user->id, Curriculum::factory()->create());

    $resolved = service()->userFor($enrollment);

    $this->assertNotNull($resolved);
    $this->assertSame('Grace', $resolved->name);
});

test('userFor throws when the host model is misconfigured', function () {
    $user = $this->learner();
    $enrollment = service()->enroll($user->id, Curriculum::factory()->create());

    config()->set('laravel-courses.user_model', 'App\\Models\\NotReal');

    $this->expectException(\RuntimeException::class);
    service()->userFor($enrollment);
});

test('an enrollment reports expiry', function () {
    $user = $this->learner();

    $live = service()->enroll(
        $user->id,
        Curriculum::factory()->create(),
        expiresAt: now()->addDay()->toDateTimeImmutable(),
    );
    $this->assertFalse($live->isExpired());

    $stale = service()->enroll(
        $user->id,
        Course::factory()->create(),
        expiresAt: now()->subDay()->toDateTimeImmutable(),
    );
    $this->assertTrue($stale->isExpired());
});
