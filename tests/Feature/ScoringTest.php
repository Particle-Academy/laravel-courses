<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

use ParticleAcademy\LaravelCourses\Models\AttemptAnswer;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ScoringService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;
use RuntimeException;

class ScoringTest extends TestCase
{
    private function scoring(): ScoringService
    {
        return $this->app->make(ScoringService::class);
    }

    private function enrol(Course $course): Enrollment
    {
        return $this->app->make(EnrollmentService::class)->enroll($this->learner()->id, $course);
    }

    /** @param array<int,array{label:string,correct:bool}> $options */
    private function question(CourseTest $test, string $type, array $options, float $points = 1, int $sort = 0): Question
    {
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

    public function test_a_correct_true_false_answer_scores_full_marks_and_passes(): void
    {
        $course = Course::factory()->create();
        [$test, $correct] = $this->trueFalseTest($course, passingScore: 50);
        $enrollment = $this->enrol($course);

        $attempt = $this->scoring()->startAttempt($enrollment, $test);
        $graded = $this->scoring()->submitAnswers($attempt, [
            ['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $correct]],
        ]);

        $this->assertSame(100.0, (float) $graded->score);
        $this->assertTrue((bool) $graded->passed);
        $this->assertNotNull($graded->finished_at);
    }

    public function test_a_wrong_answer_scores_zero_and_fails(): void
    {
        $course = Course::factory()->create();
        [$test, , $wrong] = $this->trueFalseTest($course, passingScore: 50);
        $enrollment = $this->enrol($course);

        $attempt = $this->scoring()->startAttempt($enrollment, $test);
        $graded = $this->scoring()->submitAnswers($attempt, [
            ['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $wrong]],
        ]);

        $this->assertSame(0.0, (float) $graded->score);
        $this->assertFalse((bool) $graded->passed);
    }

    public function test_multiple_select_requires_the_exact_set(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $q = $this->question($test, 'multiple_select', [
            ['label' => 'A', 'correct' => true],
            ['label' => 'B', 'correct' => true],
            ['label' => 'C', 'correct' => false],
        ]);
        $ids = $q->options->pluck('id')->all();
        [$a, $b, $c] = $ids;

        // Exactly right.
        $e1 = $this->enrol($course);
        $ok = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($e1, $test),
            [['question_id' => $q->id, 'answer' => ['option_ids' => [$a, $b]]]],
        );
        $this->assertSame(100.0, (float) $ok->score);

        // A partial answer is not partial credit — it is wrong.
        $e2 = $this->enrol($course);
        $partial = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($e2, $test),
            [['question_id' => $q->id, 'answer' => ['option_ids' => [$a]]]],
        );
        $this->assertSame(0.0, (float) $partial->score);

        // Right ones plus a wrong one is also wrong.
        $e3 = $this->enrol($course);
        $over = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($e3, $test),
            [['question_id' => $q->id, 'answer' => ['option_ids' => [$a, $b, $c]]]],
        );
        $this->assertSame(0.0, (float) $over->score);
    }

    public function test_multiple_select_ignores_the_order_answers_arrive_in(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $q = $this->question($test, 'multiple_select', [
            ['label' => 'A', 'correct' => true],
            ['label' => 'B', 'correct' => true],
        ]);
        [$a, $b] = $q->options->pluck('id')->all();

        $graded = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($this->enrol($course), $test),
            [['question_id' => $q->id, 'answer' => ['option_ids' => [$b, $a]]]],
        );

        $this->assertSame(100.0, (float) $graded->score);
    }

    public function test_an_unanswered_question_is_graded_wrong_rather_than_skipped(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $q1 = $this->question($test, 'true_false', [
            ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
        ], sort: 0);
        $this->question($test, 'true_false', [
            ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
        ], sort: 1);

        $attempt = $this->scoring()->startAttempt($this->enrol($course), $test);
        $graded = $this->scoring()->submitAnswers($attempt, [
            ['question_id' => $q1->id, 'answer' => ['option_id' => $q1->options->firstWhere('is_correct', true)->id]],
        ]);

        // Two questions, one answered correctly — 50%, and BOTH get an answer
        // row, so a grader sees the omission rather than a short list.
        $this->assertSame(50.0, (float) $graded->score);
        $this->assertSame(2, $graded->answers()->count());
    }

    public function test_points_are_weighted_not_counted(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);

        $cheap = $this->question($test, 'true_false', [
            ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
        ], points: 1, sort: 0);
        $dear = $this->question($test, 'true_false', [
            ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
        ], points: 9, sort: 1);

        // Answer only the expensive one correctly: 9 of 10 points, not 1 of 2.
        $graded = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($this->enrol($course), $test),
            [['question_id' => $dear->id, 'answer' => ['option_id' => $dear->options->firstWhere('is_correct', true)->id]]],
        );

        $this->assertSame(90.0, (float) $graded->score);
        $this->assertSame(10.0, (float) $graded->max_score);
        $this->assertNotNull($cheap);
    }

    public function test_a_short_answer_leaves_the_attempt_awaiting_a_human(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $q = $this->question($test, 'short_answer', []);

        $graded = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($this->enrol($course), $test),
            [['question_id' => $q->id, 'answer' => 'Because the sky scatters blue light.']],
        );

        // `passed` is NULL, not false — an ungraded attempt has no verdict yet,
        // and treating it as a failure would deny a certificate that was earned.
        $this->assertNull($graded->passed);
        $this->assertNull($graded->answers()->first()->is_correct);
    }

    public function test_grading_a_short_answer_recalculates_the_verdict(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $q = $this->question($test, 'short_answer', [], points: 4);

        $attempt = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($this->enrol($course), $test),
            [['question_id' => $q->id, 'answer' => 'An answer.']],
        );
        $this->assertNull($attempt->passed);

        /** @var AttemptAnswer $answer */
        $answer = $attempt->answers()->first();
        $this->scoring()->gradeShortAnswer($answer, isCorrect: true, points: 4.0);

        $recalculated = $attempt->refresh();
        $this->assertTrue((bool) $recalculated->passed);
        $this->assertSame(100.0, (float) $recalculated->score);
    }

    public function test_a_finished_attempt_cannot_be_resubmitted(): void
    {
        $course = Course::factory()->create();
        [$test, $correct] = $this->trueFalseTest($course);
        $attempt = $this->scoring()->startAttempt($this->enrol($course), $test);

        $this->scoring()->submitAnswers($attempt, [
            ['question_id' => $test->questions()->value('id'), 'answer' => ['option_id' => $correct]],
        ]);

        // Without this, a learner could resubmit until they passed.
        $this->expectException(RuntimeException::class);
        $this->scoring()->submitAnswers($attempt->refresh(), []);
    }

    public function test_attempts_are_numbered_and_capped(): void
    {
        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'max_attempts' => 2]);
        $enrollment = $this->enrol($course);

        $this->assertSame(1, $this->scoring()->startAttempt($enrollment, $test)->attempt_number);
        $this->assertSame(2, $this->scoring()->startAttempt($enrollment, $test)->attempt_number);

        $this->expectException(RuntimeException::class);
        $this->scoring()->startAttempt($enrollment, $test);
    }

    public function test_the_configured_default_passing_score_applies_when_a_test_declares_none(): void
    {
        config()->set('laravel-courses.defaults.passing_score', 90);

        $course = Course::factory()->create();
        $test = CourseTest::factory()->create(['course_id' => $course->id, 'passing_score' => null]);
        $q1 = $this->question($test, 'true_false', [
            ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
        ], sort: 0);
        $this->question($test, 'true_false', [
            ['label' => 'True', 'correct' => true], ['label' => 'False', 'correct' => false],
        ], sort: 1);

        // 50% — comfortably passing under the package default of 70, failing
        // under the host's 90. The host setting is what must win.
        $graded = $this->scoring()->submitAnswers(
            $this->scoring()->startAttempt($this->enrol($course), $test),
            [['question_id' => $q1->id, 'answer' => ['option_id' => $q1->options->firstWhere('is_correct', true)->id]]],
        );

        $this->assertSame(50.0, (float) $graded->score);
        $this->assertFalse((bool) $graded->passed);
    }
}
