<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\ProgressTest;

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

function progress(): ProgressService {
    return app()->make(ProgressService::class);
}

function enrollments(): EnrollmentService {
    return app()->make(EnrollmentService::class);
}

test('marking a lesson complete is idempotent', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $enrollment = enrollments()->enroll($this->learner()->id, $course);

    progress()->markLessonComplete($enrollment, $lesson);
    progress()->markLessonComplete($enrollment, $lesson);

    // Re-reading a lesson should not inflate progress.
    $this->assertDatabaseCount('lesson_completions', 1);
});

test('a lesson outside the enrollment is rejected', function () {
    $enrolled = Course::factory()->create();
    $other    = Course::factory()->create();
    $stray    = Lesson::factory()->create(['course_id' => $other->id]);
    $enrollment = enrollments()->enroll($this->learner()->id, $enrolled);

    $this->expectException(RuntimeException::class);
    progress()->markLessonComplete($enrollment, $stray);
});

test('summary counts lessons and tests across a curriculum', function () {
    $curriculum = Curriculum::factory()->create();
    $a = Course::factory()->create();
    $b = Course::factory()->create();
    $curriculum->courses()->attach([$a->id => ['sort_order' => 0], $b->id => ['sort_order' => 1]]);

    $l1 = Lesson::factory()->create(['course_id' => $a->id]);
    Lesson::factory()->create(['course_id' => $b->id]);
    CourseTest::factory()->create(['course_id' => $a->id]);

    $enrollment = enrollments()->enroll($this->learner()->id, $curriculum);
    progress()->markLessonComplete($enrollment, $l1);

    $summary = progress()->summary($enrollment->refresh());

    $this->assertSame(2, $summary['lessons_total']);
    $this->assertSame(1, $summary['lessons_completed']);
    $this->assertSame(50.0, $summary['lessons_percent']);
    $this->assertSame(1, $summary['tests_total']);
    $this->assertSame(0, $summary['tests_passed']);
    // Lessons and tests are weighted equally per item: 1 of 3.
    $this->assertSame(33.33, $summary['overall_percent']);
});

test('an empty target is never complete', function () {
    $course = Course::factory()->create();
    $enrollment = enrollments()->enroll($this->learner()->id, $course);

    // A course with no lessons and no tests must not read as 100% — that
    // would certify a learner for an empty course.
    $this->assertFalse(progress()->isFullyComplete($enrollment));
});

test('finishing every lesson and test auto completes the enrollment', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    [$test, $correct] = $this->trueFalseTest($course, passingScore: 50);

    $enrollment = enrollments()->enroll($this->learner()->id, $course);
    progress()->markLessonComplete($enrollment, $lesson);

    $scoring = $this->app->make(ScoringService::class);
    $scoring->submitAnswers(
        $scoring->startAttempt($enrollment, $test),
        [['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $correct]]],
    );

    // Passing the last requirement settles the enrollment without the host
    // having to poll for it.
    $this->assertSame(EnrollmentStatus::Completed, $enrollment->refresh()->status);
});

test('a failed test leaves the enrollment active', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    [$test, , $wrong] = $this->trueFalseTest($course, passingScore: 50);

    $enrollment = enrollments()->enroll($this->learner()->id, $course);
    progress()->markLessonComplete($enrollment, $lesson);

    $scoring = $this->app->make(ScoringService::class);
    $scoring->submitAnswers(
        $scoring->startAttempt($enrollment, $test),
        [['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $wrong]]],
    );

    $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status);
});

test('module and lesson level tests count toward progress', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

    CourseTest::factory()->create(['course_id' => null, 'module_id' => $module->id]);
    CourseTest::factory()->create(['course_id' => null, 'lesson_id' => $lesson->id]);

    $enrollment = enrollments()->enroll($this->learner()->id, $course);
    progress()->markLessonComplete($enrollment, $lesson);

    $summary = progress()->summary($enrollment->refresh());

    // The schema permits a quiz on a module or a lesson. Progress that
    // could not see them certified learners who had not passed them.
    $this->assertSame(2, $summary['tests_total']);
    $this->assertFalse(progress()->isFullyComplete($enrollment));
});

test('module and lesson tests are scoped to the enrolled courses', function () {
    $mine  = Course::factory()->create();
    $other = Course::factory()->create();

    $myModule    = Module::factory()->create(['course_id' => $mine->id]);
    $otherModule = Module::factory()->create(['course_id' => $other->id]);
    $otherLesson = Lesson::factory()->create(['course_id' => $other->id]);

    CourseTest::factory()->create(['course_id' => null, 'module_id' => $myModule->id]);
    CourseTest::factory()->create(['course_id' => null, 'module_id' => $otherModule->id]);
    CourseTest::factory()->create(['course_id' => null, 'lesson_id' => $otherLesson->id]);

    $enrollment = enrollments()->enroll($this->learner()->id, $mine);

    // Widening which tests count must not widen it to somebody else's
    // course — the obvious way to get this wrong is an unscoped orWhere.
    $this->assertSame(1, progress()->summary($enrollment)['tests_total']);
});

test('a test counts once even when it names several levels', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create(['course_id' => $course->id]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'module_id' => $module->id]);

    CourseTest::factory()->create([
        'course_id' => $course->id,
        'module_id' => $module->id,
        'lesson_id' => $lesson->id,
    ]);

    $enrollment = enrollments()->enroll($this->learner()->id, $course);

    // One row matching three branches of the same OR must not be counted
    // three times, or the denominator inflates and nobody can finish.
    $this->assertSame(1, progress()->summary($enrollment)['tests_total']);
});

test('a curriculum sees module tests across all its courses', function () {
    $curriculum = Curriculum::factory()->create();
    $a = Course::factory()->create();
    $b = Course::factory()->create();
    $curriculum->courses()->attach([$a->id => ['sort_order' => 0], $b->id => ['sort_order' => 1]]);

    CourseTest::factory()->create([
        'course_id' => null,
        'module_id' => Module::factory()->create(['course_id' => $a->id])->id,
    ]);
    CourseTest::factory()->create([
        'course_id' => null,
        'lesson_id' => Lesson::factory()->create(['course_id' => $b->id])->id,
    ]);

    $enrollment = enrollments()->enroll($this->learner()->id, $curriculum);

    $this->assertSame(2, progress()->summary($enrollment)['tests_total']);
});
