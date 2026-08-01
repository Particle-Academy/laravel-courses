<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Services;

use Illuminate\Support\Collection;
use ParticleAcademy\LaravelCourses\Events\LessonCompleted;
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

        $courseIds = match (true) {
            $target instanceof Course     => collect([$target->getKey()]),
            $target instanceof Curriculum => $target->courses()->pluck('courses.id'),
            default                       => collect(),
        };

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return $this->testIdsForCourses($courseIds);
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
