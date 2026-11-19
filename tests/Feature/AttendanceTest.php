<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\AttendanceTest;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus;
use ParticleAcademy\LaravelCourses\Events\AttendanceRecorded;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;
use RuntimeException;

/**
 * In-person courses: sold and taught live, with no online material, and
 * still tracked to completion — by hours attended rather than lessons read.
 */

function progress(): ProgressService {
    return app()->make(ProgressService::class);
}

function enroll(Course|Curriculum $target) {
    return app()->make(EnrollmentService::class)->enroll(test()->learner()->id, $target);
}

function inPerson(float $hours = 8, array $overrides = []): Course {
    return Course::factory()->create(array_merge([
        'delivery_mode' => 'in_person',
        'hours'         => $hours,
    ], $overrides))->fresh();
}

test('an in person course requires its hours', function () {
    $enrollment = enroll(inPerson(8));

    $summary = progress()->summary($enrollment);

    $this->assertSame(8.0, $summary['hours_required']);
    $this->assertSame(0.0, $summary['hours_attended']);
    $this->assertSame(0.0, $summary['overall_percent']);
    $this->assertFalse(progress()->isFullyComplete($enrollment));
});

test('attending part of the hours is partial progress', function () {
    $enrollment = enroll(inPerson(8));

    progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('today'));

    $summary = progress()->summary($enrollment->refresh());
    $this->assertSame(4.0, $summary['hours_attended']);
    $this->assertSame(50.0, $summary['hours_percent']);
    $this->assertSame(50.0, $summary['overall_percent']);
    $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
});

test('attending the last hour completes the enrollment', function () {
    Event::fake([AttendanceRecorded::class]);
    $enrollment = enroll(inPerson(8));

    progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('-1 day'));
    progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('today'));

    // Same contract as passing the last test: the host does not poll.
    $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
    Event::assertDispatchedTimes(AttendanceRecorded::class, 2);
});

test('extra hours cap at one hundred percent', function () {
    $enrollment = enroll(inPerson(4));

    progress()->recordAttendance($enrollment, 6, new \DateTimeImmutable('today'));

    $this->assertSame(100.0, progress()->summary($enrollment->refresh())['overall_percent']);
});

test('an in person course with no hours never completes by itself', function () {
    $enrollment = enroll(inPerson(0, ['hours' => null]));

    progress()->recordAttendance($enrollment, 3, new \DateTimeImmutable('today'));

    // No target to reach is not the same as reaching it. An admin
    // completes this one.
    $this->assertFalse(progress()->isFullyComplete($enrollment->refresh()));
    $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
});

test('online material on an in person course does not count', function () {
    $course = inPerson(8);
    Lesson::factory()->count(3)->create(['course_id' => $course->id]);
    [$test] = $this->trueFalseTest($course);

    $enrollment = enroll($course);
    progress()->recordAttendance($enrollment, 8, new \DateTimeImmutable('today'));

    // This is what lets online material be authored on a course that is
    // still taught in person: drafting lessons must not strand the people
    // who already bought the in-person version.
    $summary = progress()->summary($enrollment->refresh());
    $this->assertSame(0, $summary['lessons_total']);
    $this->assertSame(0, $summary['tests_total']);
    $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
});

test('switching to online makes the material count', function () {
    $course = inPerson(8);
    Lesson::factory()->count(2)->create(['course_id' => $course->id]);
    $enrollment = enroll($course);

    $course->update(['delivery_mode' => 'online']);

    $summary = progress()->summary($enrollment->refresh());
    $this->assertSame(2, $summary['lessons_total']);
    $this->assertSame(0.0, $summary['hours_required']);
});

test('a hybrid course needs both halves', function () {
    $course = Course::factory()->create([
        'delivery_mode'    => 'hybrid',
        'hours'            => 8,
        'attendance_hours' => 4,
    ])->fresh();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $enrollment = enroll($course);

    progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('today'));

    $summary = progress()->summary($enrollment->refresh());
    $this->assertSame(4.0, $summary['hours_required']);
    // Lessons at 0%, hours at 100% — averaged, not added, as the units differ.
    $this->assertSame(50.0, $summary['overall_percent']);
    $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

    progress()->markLessonComplete($enrollment, $lesson);
    $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
});

test('a hybrid course without attendance hours tracks online only', function () {
    // On hybrid, `hours` is the WHOLE course; reading it as seat time
    // would demand every hour be attended in person.
    $course = Course::factory()->create(['delivery_mode' => 'hybrid', 'hours' => 8])->fresh();

    $this->assertSame(0.0, $course->requiredAttendanceHours());
});

test('a curriculum adds up its in person hours', function () {
    $curriculum = Curriculum::factory()->create();
    $a = inPerson(8);
    $b = inPerson(4);
    $online = Course::factory()->create(['delivery_mode' => 'online', 'hours' => 16]);
    $curriculum->courses()->attach([
        $a->id => ['sort_order' => 0],
        $b->id => ['sort_order' => 1],
        $online->id => ['sort_order' => 2],
    ]);

    $this->assertSame(12.0, progress()->summary(enroll($curriculum))['hours_required']);
});

test('attendance is refused for an online only course', function () {
    $enrollment = enroll(Course::factory()->create(['delivery_mode' => 'online']));

    $this->expectException(RuntimeException::class);
    progress()->recordAttendance($enrollment, 2, new \DateTimeImmutable('today'));
});

test('hours must be plausible', function () {
    $enrollment = enroll(inPerson(8));

    $this->expectException(RuntimeException::class);
    progress()->recordAttendance($enrollment, 0, new \DateTimeImmutable('today'));
});

test('an admin records attendance over the api', function () {
    $this->app->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
        public function allows(Request $request): bool
        {
            return true;
        }
    });
    $enrollment = enroll(inPerson(8));

    $this->postJson("api/courses/enrollments/{$enrollment->id}/attendances", [
        'hours'       => 8,
        'attended_on' => now()->toDateString(),
        'notes'       => 'Saturday class',
    ])->assertCreated()->assertJsonPath('data.hours', 8);

    $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);

    $this->getJson("api/courses/enrollments/{$enrollment->id}/attendances")
        ->assertOk()->assertJsonCount(1, 'data');

    // A date in the future is a typo or a lie, either way not attendance.
    $this->postJson("api/courses/enrollments/{$enrollment->id}/attendances", [
        'hours'       => 2,
        'attended_on' => now()->addWeek()->toDateString(),
    ])->assertUnprocessable();
});
