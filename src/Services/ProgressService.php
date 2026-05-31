<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Services;

use Illuminate\Support\Collection;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\LessonCompletion;
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

        if ($this->isFullyComplete($enrollment->refresh())) {
            $this->enrollments->complete($enrollment);
        }

        return $completion;
    }

    /**
     * Lesson IDs reachable from this enrollment's target.
     *
     * @return Collection<int,int>
     */
    public function lessonIdsFor(Enrollment $enrollment): Collection
    {
        $target = $enrollment->enrollable;

        if ($target instanceof Course) {
            return Lesson::query()->where('course_id', $target->getKey())->pluck('id');
        }

        if ($target instanceof Curriculum) {
            $courseIds = $target->courses()->pluck('courses.id');

            return Lesson::query()->whereIn('course_id', $courseIds)->pluck('id');
        }

        return collect();
    }

    /**
     * Test IDs reachable from this enrollment's target.
     *
     * @return Collection<int,int>
     */
    public function testIdsFor(Enrollment $enrollment): Collection
    {
        $target = $enrollment->enrollable;

        if ($target instanceof Course) {
            return Test::query()->where('course_id', $target->getKey())->pluck('id');
        }

        if ($target instanceof Curriculum) {
            $courseIds = $target->courses()->pluck('courses.id');

            return Test::query()->whereIn('course_id', $courseIds)->pluck('id');
        }

        return collect();
    }

    /**
     * @return array{
     *   lessons_total:int,
     *   lessons_completed:int,
     *   lessons_percent:float,
     *   tests_total:int,
     *   tests_passed:int,
     *   tests_percent:float,
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

        $lessonsPercent = $lessonsTotal > 0 ? round(($lessonsCompleted / $lessonsTotal) * 100, 2) : 0.0;
        $testsPercent   = $testsTotal > 0 ? round(($testsPassed / $testsTotal) * 100, 2) : 0.0;

        $weightTotal     = $lessonsTotal + $testsTotal;
        $overallPercent  = $weightTotal > 0
            ? round((($lessonsCompleted + $testsPassed) / $weightTotal) * 100, 2)
            : 0.0;

        return [
            'lessons_total'     => $lessonsTotal,
            'lessons_completed' => $lessonsCompleted,
            'lessons_percent'   => $lessonsPercent,
            'tests_total'       => $testsTotal,
            'tests_passed'      => $testsPassed,
            'tests_percent'     => $testsPercent,
            'overall_percent'   => $overallPercent,
        ];
    }

    public function isFullyComplete(Enrollment $enrollment): bool
    {
        $s = $this->summary($enrollment);

        return $s['lessons_total'] > 0 || $s['tests_total'] > 0
            ? $s['lessons_completed'] === $s['lessons_total'] && $s['tests_passed'] === $s['tests_total']
            : false;
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
