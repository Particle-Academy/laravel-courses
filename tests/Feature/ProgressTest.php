<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

use ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\Module;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Services\ScoringService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;
use RuntimeException;

class ProgressTest extends TestCase
{
    private function progress(): ProgressService
    {
        return $this->app->make(ProgressService::class);
    }

    private function enrollments(): EnrollmentService
    {
        return $this->app->make(EnrollmentService::class);
    }

    public function test_marking_a_lesson_complete_is_idempotent(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $enrollment = $this->enrollments()->enroll($this->learner()->id, $course);

        $this->progress()->markLessonComplete($enrollment, $lesson);
        $this->progress()->markLessonComplete($enrollment, $lesson);

        // Re-reading a lesson should not inflate progress.
        $this->assertDatabaseCount('lesson_completions', 1);
    }

    public function test_a_lesson_outside_the_enrollment_is_rejected(): void
    {
        $enrolled = Course::factory()->create();
        $other    = Course::factory()->create();
        $stray    = Lesson::factory()->create(['course_id' => $other->id]);
        $enrollment = $this->enrollments()->enroll($this->learner()->id, $enrolled);

        $this->expectException(RuntimeException::class);
        $this->progress()->markLessonComplete($enrollment, $stray);
    }

    public function test_summary_counts_lessons_and_tests_across_a_curriculum(): void
    {
        $curriculum = Curriculum::factory()->create();
        $a = Course::factory()->create();
        $b = Course::factory()->create();
        $curriculum->courses()->attach([$a->id => ['sort_order' => 0], $b->id => ['sort_order' => 1]]);

        $l1 = Lesson::factory()->create(['course_id' => $a->id]);
        Lesson::factory()->create(['course_id' => $b->id]);
        CourseTest::factory()->create(['course_id' => $a->id]);

        $enrollment = $this->enrollments()->enroll($this->learner()->id, $curriculum);
        $this->progress()->markLessonComplete($enrollment, $l1);

        $summary = $this->progress()->summary($enrollment->refresh());

        $this->assertSame(2, $summary['lessons_total']);
        $this->assertSame(1, $summary['lessons_completed']);
        $this->assertSame(50.0, $summary['lessons_percent']);
        $this->assertSame(1, $summary['tests_total']);
        $this->assertSame(0, $summary['tests_passed']);
        // Lessons and tests are weighted equally per item: 1 of 3.
        $this->assertSame(33.33, $summary['overall_percent']);
    }

    public function test_an_empty_target_is_never_complete(): void
    {
        $course = Course::factory()->create();
        $enrollment = $this->enrollments()->enroll($this->learner()->id, $course);

        // A course with no lessons and no tests must not read as 100% — that
        // would certify a learner for an empty course.
        $this->assertFalse($this->progress()->isFullyComplete($enrollment));
    }

    public function test_finishing_every_lesson_and_test_auto_completes_the_enrollment(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        [$test, $correct] = $this->trueFalseTest($course, passingScore: 50);

        $enrollment = $this->enrollments()->enroll($this->learner()->id, $course);
        $this->progress()->markLessonComplete($enrollment, $lesson);

        $scoring = $this->app->make(ScoringService::class);
        $scoring->submitAnswers(
            $scoring->startAttempt($enrollment, $test),
            [['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $correct]]],
        );

        // Passing the last requirement settles the enrollment without the host
        // having to poll for it.
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
    }

    public function test_a_failed_test_leaves_the_enrollment_active(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        [$test, , $wrong] = $this->trueFalseTest($course, passingScore: 50);

        $enrollment = $this->enrollments()->enroll($this->learner()->id, $course);
        $this->progress()->markLessonComplete($enrollment, $lesson);

        $scoring = $this->app->make(ScoringService::class);
        $scoring->submitAnswers(
            $scoring->startAttempt($enrollment, $test),
            [['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $wrong]]],
        );

        $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status);
    }

    /**
     * Documents a real gap rather than asserting the ideal.
     *
     * `tests` declares `course_id`, `module_id` and `lesson_id` as three
     * independent nullable columns, so a quiz can legitimately hang off a
     * module or a lesson. `ProgressService::testIdsFor()` only ever queries
     * `course_id` — so a module- or lesson-level quiz is invisible to progress,
     * and an enrollment reads as fully complete without it ever being passed.
     *
     * Left as a documented limitation, not silently "fixed": changing which
     * tests count changes completion (and therefore certification) for existing
     * enrollments, which is a host-visible decision rather than ours to make
     * quietly. Attach tests at course level until this is resolved.
     */
    public function test_KNOWN_GAP_module_and_lesson_level_tests_do_not_count_toward_progress(): void
    {
        $course = Course::factory()->create();
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

        CourseTest::factory()->create(['course_id' => null, 'module_id' => $module->id]);
        CourseTest::factory()->create(['course_id' => null, 'lesson_id' => $lesson->id]);

        $enrollment = $this->enrollments()->enroll($this->learner()->id, $course);
        $this->progress()->markLessonComplete($enrollment, $lesson);

        $summary = $this->progress()->summary($enrollment->refresh());

        $this->assertSame(0, $summary['tests_total'], 'Module/lesson tests are not counted.');
        $this->assertTrue(
            $this->progress()->isFullyComplete($enrollment),
            'Enrollment reads complete despite two unpassed quizzes.',
        );
    }
}
