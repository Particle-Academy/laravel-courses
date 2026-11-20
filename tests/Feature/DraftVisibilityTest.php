<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\DraftVisibilityTest;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\Module;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;

/**
 * Only published content is public. A draft (and everything hung off it) is
 * served only to a request the host's AuthorizesCourseAdmin rule allows.
 */

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

/** @return array{course:Course,module:Module,lesson:Lesson,test:CourseTest,question:Question} */
function draftCourse(): array
{
    $course = Course::factory()->create(['slug' => 'secret-draft', 'title' => 'Secret Draft', 'is_published' => false]);
    $module = Module::factory()->create(['course_id' => $course->id, 'slug' => 'draft-module']);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'slug' => 'draft-lesson']);
    $test = CourseTest::factory()->create(['course_id' => $course->id, 'slug' => 'draft-quiz', 'title' => 'Draft Quiz']);
    $question = Question::factory()->create(['test_id' => $test->id, 'prompt' => 'A draft prompt?']);
    $question->options()->create(['label' => 'Draft option', 'is_correct' => true]);

    return compact('course', 'module', 'lesson', 'test', 'question');
}

/** @return array<string,string> */
function draftRoutes(array $d): array
{
    return [
        'course'    => 'api/courses/courses/secret-draft',
        'modules'   => 'api/courses/courses/secret-draft/modules',
        'module'    => 'api/courses/courses/secret-draft/modules/draft-module',
        'lessons'   => 'api/courses/courses/secret-draft/lessons',
        'lesson'    => 'api/courses/courses/secret-draft/lessons/draft-lesson',
        'test'      => 'api/courses/tests/draft-quiz',
        'questions' => 'api/courses/tests/draft-quiz/questions',
        'question'  => "api/courses/tests/draft-quiz/questions/{$d['question']->id}",
        'options'   => "api/courses/questions/{$d['question']->id}/options",
    ];
}

test('a draft course and its content are a 404 to the public', function () {
    $d = draftCourse();

    foreach (draftRoutes($d) as $what => $uri) {
        $this->getJson($uri)->assertNotFound();
    }
});

test('a draft course is a 404 to a signed-in user who is not an author', function () {
    $d = draftCourse();
    $author = $this->learner('Author');
    authorIs($author->id);

    $this->actingAs($this->learner('Other'));
    foreach (draftRoutes($d) as $what => $uri) {
        $this->getJson($uri)->assertNotFound();
    }
});

test('listings leave drafts out for the public, whatever the query asks', function () {
    draftCourse();
    Course::factory()->create(['slug' => 'live-course', 'is_published' => true]);
    Curriculum::factory()->create(['slug' => 'draft-path', 'is_published' => false]);
    Curriculum::factory()->create(['slug' => 'live-path', 'is_published' => true]);

    foreach (['api/courses/courses', 'api/courses/courses?published_only=0'] as $uri) {
        expect(collect($this->getJson($uri)->json('data'))->pluck('slug')->all())->toBe(['live-course']);
    }
    expect(collect($this->getJson('api/courses/curriculums')->json('data'))->pluck('slug')->all())->toBe(['live-path']);
    expect(collect($this->getJson('api/courses/tests')->json('data'))->pluck('slug')->all())->not->toContain('draft-quiz');
    $this->getJson('api/courses/curriculums/draft-path')->assertNotFound();
});

test('a published curriculum does not list its draft courses to the public', function () {
    $d = draftCourse();
    $path = Curriculum::factory()->create(['slug' => 'live-path', 'is_published' => true]);
    $live = Course::factory()->create(['slug' => 'live-course', 'is_published' => true]);
    $path->courses()->attach([$d['course']->id, $live->id]);

    $response = $this->getJson('api/courses/curriculums/live-path')->assertOk();

    expect(collect($response->json('data.courses'))->pluck('slug')->all())->toBe(['live-course']);
});

test('a learner cannot self-enrol in a draft course', function () {
    draftCourse();
    $learner = $this->learner();

    $this->actingAs($learner)
        ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'secret-draft'])
        ->assertUnprocessable();

    $this->assertDatabaseCount('enrollments', 0);
});

test('the author sees the draft and everything in it', function () {
    $d = draftCourse();
    $author = $this->learner('Author');
    authorIs($author->id);

    $this->actingAs($author);
    foreach (draftRoutes($d) as $what => $uri) {
        $this->getJson($uri)->assertOk();
    }
    expect(collect($this->getJson('api/courses/courses')->json('data'))->pluck('slug')->all())->toContain('secret-draft');
    // published_only still narrows an author's listing.
    expect(collect($this->getJson('api/courses/courses?published_only=1')->json('data'))->pluck('slug')->all())->not->toContain('secret-draft');
});
