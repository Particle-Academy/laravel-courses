<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Services;

use Illuminate\Support\Collection;
use ParticleAcademy\LaravelCourses\Events\AttendanceRecorded;
use ParticleAcademy\LaravelCourses\Events\LessonCompleted;
use ParticleAcademy\LaravelCourses\Models\Attendance;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\LessonCompletion;
use ParticleAcademy\LaravelCourses\Models\Module;
use ParticleAcademy\LaravelCourses\Models\Test;
use RuntimeException;

class ProgressService
{
    public function __construct(private readonly EnrollmentService $enrollments)
    {
    }

    public function markLessonComplete(Enrollment $enrollment, Lesson $lesson): LessonCompletion
    {
        $this->assertLessonInScope($enrollment, $lesson);

        $completion = LessonCompletion::firstOrCreate(
            [
                'enrollment_id' => $enrollment->getKey(),
                'lesson_id'     => $lesson->getKey(),
            ],
            ['completed_at' => now()],
        );

        if ($completion->wasRecentlyCreated) {
            LessonCompleted::dispatch($enrollment, $lesson, $completion);
        }

        if ($this->isFullyComplete($enrollment->refresh())) {
            $this->enrollments->complete($enrollment);
        }

        return $completion;
    }

    /**
     * Record a session the learner attended in person.
     *
     * The in-person counterpart of markLessonComplete(): attending the last
     * required hour settles the enrollment the same way passing the last test
     * does.
     */
    public function recordAttendance(
        Enrollment $enrollment,
        float $hours,
        \DateTimeInterface $attendedOn,
        int|string|null $recordedByUserId = null,
        ?string $notes = null,
    ): Attendance {
        if ($hours <= 0 || $hours > 24) {
            throw new RuntimeException('Attendance hours must be more than 0 and at most 24.');
        }

        if (! $this->coursesFor($enrollment)->contains(fn (Course $course): bool => $course->isInPerson())) {
            throw new RuntimeException(
                "Enrollment #{$enrollment->getKey()} has no in-person course to attend.",
            );
        }

        $attendance = $enrollment->attendances()->create([
            'attended_on'         => $attendedOn,
            'hours'               => $hours,
            'recorded_by_user_id' => $recordedByUserId,
            'notes'               => $notes,
        ]);

        AttendanceRecorded::dispatch($enrollment, $attendance);

        if ($this->isFullyComplete($enrollment->refresh())) {
            $this->enrollments->complete($enrollment);
        }

        return $attendance;
    }

    /**
     * Courses this enrollment covers: the course itself, or every course in
     * the curriculum.
     *
     * @return Collection<int,Course>
     */
    public function coursesFor(Enrollment $enrollment): Collection
    {
        $target = $enrollment->enrollable;

        return match (true) {
            $target instanceof Course     => collect([$target]),
            $target instanceof Curriculum => $target->courses()->get(),
            default                       => collect(),
        };
    }

    /**
     * Lesson IDs reachable from this enrollment's target.
     *
     * @return Collection<int,int>
     */
    public function lessonIdsFor(Enrollment $enrollment): Collection
    {
        $courseIds = $this->onlineCourseIdsFor($enrollment);

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return Lesson::query()->whereIn('course_id', $courseIds)->pluck('id');
    }

    /**
     * Test IDs reachable from this enrollment's target.
     *
     * @return Collection<int,int>
     */
    public function testIdsFor(Enrollment $enrollment): Collection
    {
        $courseIds = $this->onlineCourseIdsFor($enrollment);

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return $this->testIdsForCourses($courseIds);
    }

    /** In-person hours this enrollment must attend, across all its courses. */
    public function requiredAttendanceHours(Enrollment $enrollment): float
    {
        return round((float) $this->coursesFor($enrollment)
            ->sum(fn (Course $course): float => $course->requiredAttendanceHours()), 2);
    }

    /**
     * Courses whose ONLINE material counts toward progress.
     *
     * An in_person course's lessons and tests are ignored. That is what lets a
     * course be sold and taught in person first and have online material
     * authored later: drafting lessons on it changes nothing for the people
     * already enrolled until the course is switched to hybrid or online.
     *
     * @return Collection<int,int>
     */
    private function onlineCourseIdsFor(Enrollment $enrollment): Collection
    {
        return $this->coursesFor($enrollment)
            ->filter(fn (Course $course): bool => $course->isOnline())
            ->map(fn (Course $course): int => (int) $course->getKey())
            ->values();
    }

    /**
     * Every test reachable from a set of courses — at course, module OR lesson
     * level.
     *
     * `tests` declares `course_id`, `module_id` and `lesson_id` as three
     * independent nullable columns, so a quiz can legitimately hang off any of
     * the three. This used to query `course_id` alone, which made module- and
     * lesson-level quizzes invisible to progress: an enrollment reported fully
     * complete — and therefore certifiable — with them unpassed. A schema that
     * permits an attachment the progress calculation cannot see is a schema
     * with a hole in it, not a convention to write around.
     *
     * @param  Collection<int,int>  $courseIds
     * @return Collection<int,int>
     */
    private function testIdsForCourses(Collection $courseIds): Collection
    {
        $moduleIds = Module::query()->whereIn('course_id', $courseIds)->pluck('id');
        $lessonIds = Lesson::query()->whereIn('course_id', $courseIds)->pluck('id');

        return Test::query()
            ->where(function ($query) use ($courseIds, $moduleIds, $lessonIds): void {
                $query->whereIn('course_id', $courseIds)
                    ->orWhereIn('module_id', $moduleIds)
                    ->orWhereIn('lesson_id', $lessonIds);
            })
            ->pluck('id');
    }

    /**
     * @return array{
     *   lessons_total:int,
     *   lessons_completed:int,
     *   lessons_percent:float,
     *   tests_total:int,
     *   tests_passed:int,
     *   tests_percent:float,
     *   hours_required:float,
     *   hours_attended:float,
     *   hours_percent:float,
     *   overall_percent:float,
     * }
     */
    public function summary(Enrollment $enrollment): array
    {
        $lessonIds = $this->lessonIdsFor($enrollment);
        $lessonsTotal = $lessonIds->count();
        $lessonsCompleted = $enrollment->lessonCompletions()
            ->whereIn('lesson_id', $lessonIds)
            ->count();

        $testIds = $this->testIdsFor($enrollment);
        $testsTotal = $testIds->count();
        $testsPassed = $enrollment->testAttempts()
            ->whereIn('test_id', $testIds)
            ->where('passed', true)
            ->distinct('test_id')
            ->count('test_id');

        $hoursRequired = $this->requiredAttendanceHours($enrollment);
        $hoursAttended = round((float) $enrollment->attendances()->sum('hours'), 2);

        $lessonsPercent = $lessonsTotal > 0 ? round(($lessonsCompleted / $lessonsTotal) * 100, 2) : 0.0;
        $testsPercent   = $testsTotal > 0 ? round(($testsPassed / $testsTotal) * 100, 2) : 0.0;
        // Capped: a learner who sat in on an extra session is done, not 120% done.
        $hoursPercent   = $hoursRequired > 0 ? round(min(1, $hoursAttended / $hoursRequired) * 100, 2) : 0.0;

        // Online items are weighted equally per item, as they always were.
        // Attendance is in hours, a different unit, so when an enrollment has
        // both, the two halves are averaged rather than added together.
        $weightTotal = $lessonsTotal + $testsTotal;
        $parts = [];
        if ($weightTotal > 0) {
            $parts[] = (($lessonsCompleted + $testsPassed) / $weightTotal) * 100;
        }
        if ($hoursRequired > 0) {
            $parts[] = $hoursPercent;
        }
        $overallPercent = $parts === [] ? 0.0 : round(array_sum($parts) / count($parts), 2);

        return [
            'lessons_total'     => $lessonsTotal,
            'lessons_completed' => $lessonsCompleted,
            'lessons_percent'   => $lessonsPercent,
            'tests_total'       => $testsTotal,
            'tests_passed'      => $testsPassed,
            'tests_percent'     => $testsPercent,
            'hours_required'    => $hoursRequired,
            'hours_attended'    => $hoursAttended,
            'hours_percent'     => $hoursPercent,
            'overall_percent'   => $overallPercent,
        ];
    }

    public function isFullyComplete(Enrollment $enrollment): bool
    {
        $s = $this->summary($enrollment);

        $hasOnline     = $s['lessons_total'] > 0 || $s['tests_total'] > 0;
        $hasAttendance = $s['hours_required'] > 0;

        // Nothing to do is not the same as done — an empty course must never
        // certify. An in-person course with no hours set lands here too: it is
        // completed by an admin, not by arithmetic.
        if (! $hasOnline && ! $hasAttendance) {
            return false;
        }

        $onlineDone = ! $hasOnline
            || ($s['lessons_completed'] === $s['lessons_total'] && $s['tests_passed'] === $s['tests_total']);
        $attendanceDone = ! $hasAttendance || $s['hours_attended'] >= $s['hours_required'];

        return $onlineDone && $attendanceDone;
    }

    private function assertLessonInScope(Enrollment $enrollment, Lesson $lesson): void
    {
        if (! $this->lessonIdsFor($enrollment)->contains($lesson->getKey())) {
            throw new RuntimeException(
                "Lesson #{$lesson->getKey()} is not part of enrollment #{$enrollment->getKey()}'s target.",
            );
        }
    }
}
