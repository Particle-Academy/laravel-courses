<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\AnswerKeyTest;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;

/**
 * Who receives a test's answer key: `is_correct` on the options and the
 * question's `explanation`. Hidden unless the host's AuthorizesCourseAdmin
 * rule allows the request, whatever the caller asks for.
 */

/** The host's author rule: only the named user may author. */
function authorIs(int $userId): void
{
    app()->bind(AuthorizesCourseAdmin::class, fn () => new class($userId) implements AuthorizesCourseAdmin {
        public function __construct(private readonly int $userId)
        {
        }

        public function allows(Request $request): bool
        {
            return (int) $request->user()?->getAuthIdentifier() === $this->userId;
        }
    });
}

/** @return array{course:Course,test:CourseTest,question:Question} */
function keyedQuiz(): array
{
    $course = Course::factory()->create(['slug' => 'key-course', 'is_published' => true]);
    $test = CourseTest::factory()->create(['slug' => 'key-quiz', 'course_id' => $course->id, 'max_attempts' => null]);
    $question = Question::factory()->create([
        'test_id'     => $test->id,
        'type'        => 'multiple_choice',
        'prompt'      => 'Pick the right one',
        'explanation' => 'Because it is the right one.',
        'points'      => 1,
    ]);
    $question->options()->create(['label' => 'Right', 'is_correct' => true, 'sort_order' => 0]);
    $question->options()->create(['label' => 'Wrong', 'is_correct' => false, 'sort_order' => 1]);

    return ['course' => $course, 'test' => $test, 'question' => $question];
}

/** Every route that serves questions or options to a reader. */
function readKey(\ParticleAcademy\LaravelCourses\Tests\TestCase $t, array $quiz): array
{
    return [
        'options'   => $t->getJson("api/courses/questions/{$quiz['question']->id}/options"),
        'questions' => $t->getJson('api/courses/tests/key-quiz/questions'),
        'question'  => $t->getJson("api/courses/tests/key-quiz/questions/{$quiz['question']->id}"),
        'test'      => $t->getJson('api/courses/tests/key-quiz'),
        'tests'     => $t->getJson('api/courses/tests'),
    ];
}

function assertNoKey(TestResponse $response, string $where): void
{
    $response->assertOk();
    $body = $response->getContent();
    \PHPUnit\Framework\Assert::assertStringNotContainsString('"is_correct"', $body, "{$where} carried is_correct");
    \PHPUnit\Framework\Assert::assertStringNotContainsString('"explanation"', $body, "{$where} carried the explanation");
    \PHPUnit\Framework\Assert::assertStringNotContainsString('Because it is the right one.', $body, "{$where} carried the explanation text");
}

test('an anonymous caller never receives the answer key', function () {
    $quiz = keyedQuiz();

    foreach (readKey($this, $quiz) as $where => $response) {
        assertNoKey($response, $where);
    }
});

test('asking with hide_answers=0 does not reveal the key', function () {
    $quiz = keyedQuiz();

    assertNoKey($this->getJson("api/courses/questions/{$quiz['question']->id}/options?hide_answers=0"), 'options');
    assertNoKey($this->getJson('api/courses/tests/key-quiz/questions?hide_answers=0'), 'questions');
});

test('a learner starting an attempt receives the questions without the key', function () {
    $quiz = keyedQuiz();
    $learner = $this->learner();
    $enrollment = app(EnrollmentService::class)->enroll($learner->id, $quiz['course']);

    $start = $this->actingAs($learner)
        ->postJson("api/courses/enrollments/{$enrollment->id}/tests/{$quiz['test']->id}/attempts");

    $start->assertCreated();
    expect($start->getContent())->toContain('Pick the right one');
    expect($start->getContent())->not->toContain('"is_correct"')
        ->and($start->getContent())->not->toContain('Because it is the right one.');

    // ...and nothing else they can read mid-attempt carries it either.
    foreach (readKey($this, $quiz) as $where => $response) {
        assertNoKey($response, "mid-attempt {$where}");
    }
});

test('a signed-in user who is not the author never receives the key', function () {
    $quiz = keyedQuiz();
    $author = $this->learner('Author');
    $other = $this->learner('Other');
    authorIs($author->id);

    $this->actingAs($other);
    foreach (readKey($this, $quiz) as $where => $response) {
        assertNoKey($response, $where);
    }
});

test('the author sees the key, to author the test', function () {
    $quiz = keyedQuiz();
    $author = $this->learner('Author');
    authorIs($author->id);

    $options = $this->actingAs($author)->getJson("api/courses/questions/{$quiz['question']->id}/options");
    $options->assertOk();
    expect(collect($options->json('data'))->pluck('is_correct', 'label')->all())
        ->toBe(['Right' => true, 'Wrong' => false]);

    $questions = $this->actingAs($author)->getJson('api/courses/tests/key-quiz/questions');
    expect($questions->json('data.0.explanation'))->toBe('Because it is the right one.')
        ->and($questions->json('data.0.options.0.is_correct'))->toBeTrue();

    // An author may still ask for the learner's view.
    $hidden = $this->actingAs($author)->getJson('api/courses/tests/key-quiz/questions?hide_answers=1');
    expect($hidden->getContent())->not->toContain('"is_correct"');
});
