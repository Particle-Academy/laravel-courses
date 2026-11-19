<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\ScoringTest;

use ParticleAcademy\LaravelCourses\Models\AttemptAnswer;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ScoringService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;
use RuntimeException;

function scoring(): ScoringService {
    return app()->make(ScoringService::class);
}

function enrol(Course $course): Enrollment {
    return app()->make(EnrollmentService::class)->enroll(test()->learner()->id, $course);
}

/** @param array<int,array{label:string,correct:bool}> $options */
function question(CourseTest $test, string $type, array $options, float $points = 1, int $sort = 0): Question {
    $question = Question::factory()->create([
        'test_id'    => $test->id,
        'type'       => $type,
        'points'     => $points,
        'sort_order' => $sort,
    ]);

    foreach ($options as $i => $opt) {
        $question->options()->create([
            'label'      => $opt['label'],
            'is_correct' => $opt['correct'],
            'sort_order' => $i,
        ]);
    }

    return $question->load('options');
}

test('a correct true false answer scores full marks and passes', function () {
    $course = Course::factory()->create();
    [$test, $correct] = $this->trueFalseTest($course, passingScore: 50);
    $enrollment = enrol($course);

    $attempt = scoring()->startAttempt($enrollment, $test);
    $graded = scoring()->submitAnswers($attempt, [
        ['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $correct]],
    ]);

    $this->assertSame(100.0, (float) $graded->score);
    $this->assertTrue((bool) $graded->passed);
    $this->assertNotNull($graded->finished_at);
});

test('a wrong answer scores zero and fails', function () {
    $course = Course::factory()->create();
    [$test, , $wrong] = $this->trueFalseTest($course, passingScore: 50);
    $enrollment = enrol($course);

    $attempt = scoring()->startAttempt($enrollment, $test);
    $graded = scoring()->submitAnswers($attempt, [
        ['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $wrong]],
    ]);

    $this->assertSame(0.0, (float) $graded->score);
    $this->assertFalse((bool) $graded->passed);
});

test('multiple select requires the exact set', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
    $q = question($test, 'multiple_select', [
        ['label' => 'A', 'correct' => true],
        ['label' => 'B', 'correct' => true],
        ['label' => 'C', 'correct' => false],
    ]);
    $ids = $q->options->pluck('id')->all();
    [$a, $b, $c] = $ids;

    // Exactly right.
    $e1 = enrol($course);
    $ok = scoring()->submitAnswers(
        scoring()->startAttempt($e1, $test),
        [['question_id' => $q->id, 'answer' => ['option_ids' => [$a, $b]]]],
    );
    $this->assertSame(100.0, (float) $ok->score);

    // A partial answer is not partial credit — it is wrong.
    $e2 = enrol($course);
    $partial = scoring()->submitAnswers(
        scoring()->startAttempt($e2, $test),
        [['question_id' => $q->id, 'answer' => ['option_ids' => [$a]]]],
    );
    $this->assertSame(0.0, (float) $partial->score);

    // Right ones plus a wrong one is also wrong.
    $e3 = enrol($course);
    $over = scoring()->submitAnswers(
        scoring()->startAttempt($e3, $test),
        [['question_id' => $q->id, 'answer' => ['option_ids' => [$a, $b, $c]]]],
    );
    $this->assertSame(0.0, (float) $over->score);
});

test('multiple select ignores the order answers arrive in', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
    $q = question($test, 'multiple_select', [
        ['label' => 'A', 'correct' => true],
        ['label' => 'B', 'correct' => true],
    ]);
    [$a, $b] = $q->options->pluck('id')->all();

    $graded = scoring()->submitAnswers(
        scoring()->startAttempt(enrol($course), $test),
        [['question_id' => $q->id, 'answer' => ['option_ids' => [$b, $a]]]],
    );

    $this->assertSame(100.0, (float) $graded->score);
});

test('an unanswered question is graded wrong rather than skipped', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
    $q1 = question($test, 'true_false', [
        ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
    ], sort: 0);
    question($test, 'true_false', [
        ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
    ], sort: 1);

    $attempt = scoring()->startAttempt(enrol($course), $test);
    $graded = scoring()->submitAnswers($attempt, [
        ['question_id' => $q1->id, 'answer' => ['option_id' => $q1->options->firstWhere('is_correct', true)->id]],
    ]);

    // Two questions, one answered correctly — 50%, and BOTH get an answer
    // row, so a grader sees the omission rather than a short list.
    $this->assertSame(50.0, (float) $graded->score);
    $this->assertSame(2, $graded->answers()->count());
});

test('points are weighted not counted', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);

    $cheap = question($test, 'true_false', [
        ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
    ], points: 1, sort: 0);
    $dear = question($test, 'true_false', [
        ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
    ], points: 9, sort: 1);

    // Answer only the expensive one correctly: 9 of 10 points, not 1 of 2.
    $graded = scoring()->submitAnswers(
        scoring()->startAttempt(enrol($course), $test),
        [['question_id' => $dear->id, 'answer' => ['option_id' => $dear->options->firstWhere('is_correct', true)->id]]],
    );

    $this->assertSame(90.0, (float) $graded->score);
    $this->assertSame(10.0, (float) $graded->max_score);
    $this->assertNotNull($cheap);
});

test('a short answer leaves the attempt awaiting a human', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
    $q = question($test, 'short_answer', []);

    $graded = scoring()->submitAnswers(
        scoring()->startAttempt(enrol($course), $test),
        [['question_id' => $q->id, 'answer' => 'Because the sky scatters blue light.']],
    );

    // `passed` is NULL, not false — an ungraded attempt has no verdict yet,
    // and treating it as a failure would deny a certificate that was earned.
    $this->assertNull($graded->passed);
    $this->assertNull($graded->answers()->first()->is_correct);
});

test('grading a short answer recalculates the verdict', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
    $q = question($test, 'short_answer', [], points: 4);

    $attempt = scoring()->submitAnswers(
        scoring()->startAttempt(enrol($course), $test),
        [['question_id' => $q->id, 'answer' => 'An answer.']],
    );
    $this->assertNull($attempt->passed);

    /** @var AttemptAnswer $answer */
    $answer = $attempt->answers()->first();
    scoring()->gradeShortAnswer($answer, isCorrect: true, points: 4.0);

    $recalculated = $attempt->refresh();
    $this->assertTrue((bool) $recalculated->passed);
    $this->assertSame(100.0, (float) $recalculated->score);
});

test('a finished attempt cannot be resubmitted', function () {
    $course = Course::factory()->create();
    [$test, $correct] = $this->trueFalseTest($course);
    $attempt = scoring()->startAttempt(enrol($course), $test);

    scoring()->submitAnswers($attempt, [
        ['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $correct]],
    ]);

    // Without this, a learner could resubmit until they passed.
    $this->expectException(RuntimeException::class);
    scoring()->submitAnswers($attempt->refresh(), []);
});

test('attempts are numbered and capped', function () {
    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'max_attempts' => 2]);
    $enrollment = enrol($course);

    $this->assertSame(1, scoring()->startAttempt($enrollment, $test)->attempt_number);
    $this->assertSame(2, scoring()->startAttempt($enrollment, $test)->attempt_number);

    $this->expectException(RuntimeException::class);
    scoring()->startAttempt($enrollment, $test);
});

test('the configured default passing score applies when a test declares none', function () {
    config()->set('laravel-courses.defaults.passing_score', 90);

    $course = Course::factory()->create();
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => null]);
    $q1 = question($test, 'true_false', [
        ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
    ], sort: 0);
    question($test, 'true_false', [
        ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
    ], sort: 1);

    // 50% — comfortably passing under the package default of 70, failing
    // under the host's 90. The host setting is what must win.
    $graded = scoring()->submitAnswers(
        scoring()->startAttempt(enrol($course), $test),
        [['question_id' => $q1->id, 'answer' => ['option_id' => $q1->options->firstWhere('is_correct', true)->id]]],
    );

    $this->assertSame(50.0, (float) $graded->score);
    $this->assertFalse((bool) $graded->passed);
});
