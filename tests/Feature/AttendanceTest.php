<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

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
class AttendanceTest extends TestCase
{
    private function progress(): ProgressService
    {
        return $this->app->make(ProgressService::class);
    }

    private function enroll(Course|Curriculum $target)
    {
        return $this->app->make(EnrollmentService::class)->enroll($this->learner()->id, $target);
    }

    private function inPerson(float $hours = 8, array $overrides = []): Course
    {
        return Course::factory()->create(array_merge([
            'delivery_mode' => 'in_person',
            'hours'         => $hours,
        ], $overrides))->fresh();
    }

    public function test_an_in_person_course_requires_its_hours(): void
    {
        $enrollment = $this->enroll($this->inPerson(8));

        $summary = $this->progress()->summary($enrollment);

        $this->assertSame(8.0, $summary['hours_required']);
        $this->assertSame(0.0, $summary['hours_attended']);
        $this->assertSame(0.0, $summary['overall_percent']);
        $this->assertFalse($this->progress()->isFullyComplete($enrollment));
    }

    public function test_attending_part_of_the_hours_is_partial_progress(): void
    {
        $enrollment = $this->enroll($this->inPerson(8));

        $this->progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('today'));

        $summary = $this->progress()->summary($enrollment->refresh());
        $this->assertSame(4.0, $summary['hours_attended']);
        $this->assertSame(50.0, $summary['hours_percent']);
        $this->assertSame(50.0, $summary['overall_percent']);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_attending_the_last_hour_completes_the_enrollment(): void
    {
        Event::fake([AttendanceRecorded::class]);
        $enrollment = $this->enroll($this->inPerson(8));

        $this->progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('-1 day'));
        $this->progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('today'));

        // Same contract as passing the last test: the host does not poll.
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
        Event::assertDispatchedTimes(AttendanceRecorded::class, 2);
    }

    public function test_extra_hours_cap_at_one_hundred_percent(): void
    {
        $enrollment = $this->enroll($this->inPerson(4));

        $this->progress()->recordAttendance($enrollment, 6, new \DateTimeImmutable('today'));

        $this->assertSame(100.0, $this->progress()->summary($enrollment->refresh())['overall_percent']);
    }

    public function test_an_in_person_course_with_no_hours_never_completes_by_itself(): void
    {
        $enrollment = $this->enroll($this->inPerson(0, ['hours' => null]));

        $this->progress()->recordAttendance($enrollment, 3, new \DateTimeImmutable('today'));

        // No target to reach is not the same as reaching it. An admin
        // completes this one.
        $this->assertFalse($this->progress()->isFullyComplete($enrollment->refresh()));
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_online_material_on_an_in_person_course_does_not_count(): void
    {
        $course = $this->inPerson(8);
        Lesson::factory()->count(3)->create(['course_id' => $course->id]);
        [$test] = $this->trueFalseTest($course);

        $enrollment = $this->enroll($course);
        $this->progress()->recordAttendance($enrollment, 8, new \DateTimeImmutable('today'));

        // This is what lets online material be authored on a course that is
        // still taught in person: drafting lessons must not strand the people
        // who already bought the in-person version.
        $summary = $this->progress()->summary($enrollment->refresh());
        $this->assertSame(0, $summary['lessons_total']);
        $this->assertSame(0, $summary['tests_total']);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);
    }

    public function test_switching_to_online_makes_the_material_count(): void
    {
        $course = $this->inPerson(8);
        Lesson::factory()->count(2)->create(['course_id' => $course->id]);
        $enrollment = $this->enroll($course);

        $course->update(['delivery_mode' => 'online']);

        $summary = $this->progress()->summary($enrollment->refresh());
        $this->assertSame(2, $summary['lessons_total']);
        $this->assertSame(0.0, $summary['hours_required']);
    }

    public function test_a_hybrid_course_needs_both_halves(): void
    {
        $course = Course::factory()->create([
            'delivery_mode'    => 'hybrid',
            'hours'            => 8,
            'attendance_hours' => 4,
        ])->fresh();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $enrollment = $this->enroll($course);

        $this->progress()->recordAttendance($enrollment, 4, new \DateTimeImmutable('today'));

        $summary = $this->progress()->summary($enrollment->refresh());
        $this->assertSame(4.0, $summary['hours_required']);
        // Lessons at 0%, hours at 100% — averaged, not added, as the units differ.
        $this->assertSame(50.0, $summary['overall_percent']);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        $this->progress()->markLessonComplete($enrollment, $lesson);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_a_hybrid_course_without_attendance_hours_tracks_online_only(): void
    {
        // On hybrid, `hours` is the WHOLE course; reading it as seat time
        // would demand every hour be attended in person.
        $course = Course::factory()->create(['delivery_mode' => 'hybrid', 'hours' => 8])->fresh();

        $this->assertSame(0.0, $course->requiredAttendanceHours());
    }

    public function test_a_curriculum_adds_up_its_in_person_hours(): void
    {
        $curriculum = Curriculum::factory()->create();
        $a = $this->inPerson(8);
        $b = $this->inPerson(4);
        $online = Course::factory()->create(['delivery_mode' => 'online', 'hours' => 16]);
        $curriculum->courses()->attach([
            $a->id => ['sort_order' => 0],
            $b->id => ['sort_order' => 1],
            $online->id => ['sort_order' => 2],
        ]);

        $this->assertSame(12.0, $this->progress()->summary($this->enroll($curriculum))['hours_required']);
    }

    public function test_attendance_is_refused_for_an_online_only_course(): void
    {
        $enrollment = $this->enroll(Course::factory()->create(['delivery_mode' => 'online']));

        $this->expectException(RuntimeException::class);
        $this->progress()->recordAttendance($enrollment, 2, new \DateTimeImmutable('today'));
    }

    public function test_hours_must_be_plausible(): void
    {
        $enrollment = $this->enroll($this->inPerson(8));

        $this->expectException(RuntimeException::class);
        $this->progress()->recordAttendance($enrollment, 0, new \DateTimeImmutable('today'));
    }

    public function test_an_admin_records_attendance_over_the_api(): void
    {
        $this->app->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
            public function allows(Request $request): bool
            {
                return true;
            }
        });
        $enrollment = $this->enroll($this->inPerson(8));

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
    }
}
