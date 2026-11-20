<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\LearnerRefusalTest;

use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\Test as CourseTest;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ScoringService;

/**
 * A refusal is a 4xx in the package's error style ({"message": ...}), never a
 * 500, and it writes nothing. An unauthenticated learner is a 401; a request
 * the course rules refuse is a 409 or a 422.
 */

test('an unauthenticated learner gets a 401 on every learner route, and nothing is written', function () {
    $owner = $this->learner('Owner');
    $course = Course::factory()->create(['slug' => 'free-course', 'is_published' => true, 'price' => null]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    [$test] = $this->trueFalseTest($course);
    $enrollment = app(EnrollmentService::class)->enroll($owner->id, $course);
    $attempt = app(ScoringService::class)->startAttempt($enrollment, $test);
    $certificate = app(CertificateService::class)->issue($enrollment);

    $before = [
        'enrollments' => \DB::table('enrollments')->count(),
        'completions' => \DB::table('lesson_completions')->count(),
        'attempts'    => \DB::table('test_attempts')->count(),
        'answers'     => \DB::table('attempt_answers')->count(),
        'certs'       => \DB::table('certificates')->count(),
    ];

    $calls = [
        ['getJson',    'api/courses/enrollments'],
        ['postJson',   'api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'free-course', 'user_id' => $owner->id]],
        ['getJson',    "api/courses/enrollments/{$enrollment->id}"],
        ['deleteJson', "api/courses/enrollments/{$enrollment->id}"],
        ['postJson',   "api/courses/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete"],
        ['postJson',   "api/courses/enrollments/{$enrollment->id}/tests/{$test->id}/attempts"],
        ['getJson',    "api/courses/attempts/{$attempt->id}"],
        ['postJson',   "api/courses/attempts/{$attempt->id}/submit", ['answers' => [['question_id' => 1, 'answer' => ['option_id' => 1]]]]],
        ['postJson',   "api/courses/enrollments/{$enrollment->id}/certificate"],
        ['getJson',    "api/courses/certificates/{$certificate->id}"],
        ['getJson',    "api/courses/certificates/{$certificate->id}/pdf"],
    ];

    foreach ($calls as $call) {
        [$verb, $uri] = $call;
        $response = $this->{$verb}($uri, $call[2] ?? []);
        $this->assertSame(401, $response->status(), "{$verb} {$uri} answered {$response->status()}");
        $this->assertIsString($response->json('message'), "{$verb} {$uri} has no message");
    }

    expect([
        'enrollments' => \DB::table('enrollments')->count(),
        'completions' => \DB::table('lesson_completions')->count(),
        'attempts'    => \DB::table('test_attempts')->count(),
        'answers'     => \DB::table('attempt_answers')->count(),
        'certs'       => \DB::table('certificates')->count(),
    ])->toBe($before);
    expect($enrollment->fresh()->status->value)->toBe('active');
});

test('a third attempt where two are allowed is a 409 and creates nothing', function () {
    $learner = $this->learner();
    $course = Course::factory()->create(['is_published' => true]);
    [$test] = $this->trueFalseTest($course);
    $test->update(['max_attempts' => 2]);
    $enrollment = app(EnrollmentService::class)->enroll($learner->id, $course);

    $uri = "api/courses/enrollments/{$enrollment->id}/tests/{$test->id}/attempts";
    $this->actingAs($learner)->postJson($uri)->assertCreated();
    $this->actingAs($learner)->postJson($uri)->assertCreated();

    $third = $this->actingAs($learner)->postJson($uri);

    $third->assertStatus(409);
    expect($third->json('message'))->toContain('Maximum attempts');
    $this->assertDatabaseCount('test_attempts', 2);
});

test('resubmitting a finished attempt is a 409 and changes nothing', function () {
    $learner = $this->learner();
    $course = Course::factory()->create(['is_published' => true]);
    [$test, $right, $wrong] = $this->trueFalseTest($course);
    $questionId = $test->questions()->first()->id;
    $enrollment = app(EnrollmentService::class)->enroll($learner->id, $course);
    $attempt = app(ScoringService::class)->startAttempt($enrollment, $test);

    $this->actingAs($learner)->postJson("api/courses/attempts/{$attempt->id}/submit", [
        'answers' => [['question_id' => $questionId, 'answer' => ['option_id' => $wrong]]],
    ])->assertOk();
    $scoreBefore = $attempt->fresh()->score;

    $again = $this->actingAs($learner)->postJson("api/courses/attempts/{$attempt->id}/submit", [
        'answers' => [['question_id' => $questionId, 'answer' => ['option_id' => $right]]],
    ]);

    $again->assertStatus(409);
    expect($attempt->fresh()->score)->toBe($scoreBefore);
    $this->assertDatabaseCount('attempt_answers', 1);
});

test('completing a lesson outside the enrollment is a 422 and records nothing', function () {
    $learner = $this->learner();
    $mine = Course::factory()->create(['is_published' => true]);
    $other = Course::factory()->create(['is_published' => true]);
    $foreignLesson = Lesson::factory()->create(['course_id' => $other->id]);
    $enrollment = app(EnrollmentService::class)->enroll($learner->id, $mine);

    $response = $this->actingAs($learner)
        ->postJson("api/courses/enrollments/{$enrollment->id}/lessons/{$foreignLesson->id}/complete");

    $response->assertStatus(422);
    $this->assertIsString($response->json('message'));
    $this->assertDatabaseCount('lesson_completions', 0);
});
