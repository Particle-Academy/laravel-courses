<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\AuthorizationTest;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Contracts\GatesSelfEnrollment;
use ParticleAcademy\LaravelCourses\Models\Certificate;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Support\LearnerResolver;
use ParticleAcademy\LaravelCourses\Tests\TestCase;
use RuntimeException;

/**
 * The security contract of a default install.
 *
 * A certification package's whole value is that a certificate cannot be
 * self-issued. Before this suite existed, an anonymous request could author
 * content, delete it, and mint a certificate for an arbitrary user id against
 * a default install — verified, not theorised.
 */

function allowAdmin(): void {
    app()->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
        public function allows(Request $request): bool
        {
            return true;
        }
    });
}

// ── Writes are denied by default ─────────────────────────────────────

test('an anonymous caller cannot create content', function () {
    $this->postJson('api/courses/curriculums', ['slug' => 'pwned', 'title' => 'Nope'])
        ->assertForbidden();

    $this->assertDatabaseMissing('curriculums', ['slug' => 'pwned']);
});

test('an anonymous caller cannot destroy content', function () {
    $curriculum = Curriculum::factory()->create(['slug' => 'real-curriculum']);

    $this->deleteJson('api/courses/curriculums/real-curriculum')->assertForbidden();

    $this->assertTrue(Curriculum::whereKey($curriculum->id)->exists());
});

test('an anonymous caller cannot mint a certificate', function () {
    Course::factory()->create(['slug' => 'some-course', 'is_published' => true]);

    $this->postJson('api/courses/admin/completions', [
        'user_id'     => 4242,
        'target_kind' => 'course',
        'target_slug' => 'some-course',
    ])->assertForbidden();

    // The point of the whole package: no certificate exists for a learner
    // who did nothing.
    $this->assertSame(0, Certificate::query()->count());
});

test('an anonymous caller cannot revoke a certificate', function () {
    $user = $this->learner();
    $course = Course::factory()->create();
    $enrollment = $this->app->make(EnrollmentService::class)->enroll($user->id, $course);
    $certificate = $this->app->make(\ParticleAcademy\LaravelCourses\Services\CertificateService::class)
        ->issue($enrollment);

    $this->postJson("api/courses/certificates/{$certificate->id}/revoke")->assertForbidden();

    $this->assertNull($certificate->refresh()->revoked_at);
});

test('every write route is gated', function () {
    $course = Course::factory()->create(['slug' => 'c']);
    Lesson::factory()->create(['course_id' => $course->id, 'slug' => 'l']);
    $enrollment = $this->app->make(EnrollmentService::class)->enroll($this->learner()->id, $course);
    $attendance = $enrollment->attendances()->create(['attended_on' => now(), 'hours' => 1]);

    // A table rather than one case per route: the risk is a NEW write route
    // being added outside the gated group, and a list is what makes that
    // omission visible.
    $writes = [
        ['postJson',   'api/courses/curriculums'],
        ['postJson',   'api/courses/courses'],
        ['postJson',   'api/courses/courses/c/modules'],
        ['postJson',   'api/courses/courses/c/lessons'],
        ['postJson',   'api/courses/tests'],
        ['postJson',   'api/courses/certificate-templates'],
        ['deleteJson', 'api/courses/courses/c'],
        ['deleteJson', 'api/courses/courses/c/lessons/l'],
        ['postJson',   'api/courses/admin/completions'],
        // A learner recording their own seat time is self-certification by
        // another route, so attendance reads AND writes are admin-only.
        ['getJson',    "api/courses/enrollments/{$enrollment->id}/attendances"],
        ['postJson',   "api/courses/enrollments/{$enrollment->id}/attendances"],
        ['deleteJson', "api/courses/enrollments/{$enrollment->id}/attendances/{$attendance->id}"],
    ];

    foreach ($writes as [$verb, $uri]) {
        $this->{$verb}($uri, [])->assertForbidden();
    }
});

// ── Reads stay open ──────────────────────────────────────────────────

test('reading the catalogue stays public', function () {
    Curriculum::factory()->create(['slug' => 'published-thing', 'is_published' => true]);

    $this->getJson('api/courses/curriculums')->assertOk();
    $this->getJson('api/courses/curriculums/published-thing')->assertOk();
});

test('certificate verification stays public', function () {
    $user = $this->learner();
    $enrollment = $this->app->make(EnrollmentService::class)
        ->enroll($user->id, Course::factory()->create());
    $certificate = $this->app->make(\ParticleAcademy\LaravelCourses\Services\CertificateService::class)
        ->issue($enrollment);

    // A certificate nobody can check is worthless, so this one route is
    // deliberately open.
    $this->getJson("api/courses/verify/{$certificate->verification_code}")->assertOk();
});

// ── A bound authorizer switches authoring on ─────────────────────────

test('a host binding enables authoring', function () {
    allowAdmin();

    $this->postJson('api/courses/curriculums', [
        'slug' => 'allowed', 'title' => 'Allowed',
    ])->assertCreated();

    $this->assertDatabaseHas('curriculums', ['slug' => 'allowed']);
});

// ── Learner identity cannot be claimed ───────────────────────────────

test('a caller cannot claim to be a learner by default', function () {
    $resolver = $this->app->make(LearnerResolver::class);
    $request = Request::create('/', 'POST', ['user_id' => 999]);

    // Every ownership check in the package compares against this value.
    // If an anonymous caller can set it, those checks are decorative.
    $this->expectException(RuntimeException::class);
    $resolver->resolve($request);
});

test('a caller cannot claim to be a learner via header', function () {
    $resolver = $this->app->make(LearnerResolver::class);
    $request = Request::create('/', 'POST');
    $request->headers->set('X-Learner-Id', '999');

    $this->expectException(RuntimeException::class);
    $resolver->resolve($request);
});

test('a host can opt into trusting a supplied learner id', function () {
    config()->set('laravel-courses.allow_input_user_id', true);

    $resolver = $this->app->make(LearnerResolver::class);
    $this->assertSame(999, $resolver->resolve(Request::create('/', 'POST', ['user_id' => 999])));
});

test('an authenticated user always resolves to themselves', function () {
    config()->set('laravel-courses.allow_input_user_id', true);
    $user = $this->learner();

    $request = Request::create('/', 'POST', ['user_id' => 999]);
    $request->setUserResolver(fn () => $user);

    // Even with the fallback on, a real session wins over the input — so
    // an authenticated learner cannot act as someone else.
    $this->assertSame($user->id, $this->app->make(LearnerResolver::class)->resolve($request));
});

test('a learner cannot complete another learners lesson', function () {
    config()->set('laravel-courses.allow_input_user_id', true);

    $owner = $this->learner('Owner');
    $other = $this->learner('Other');
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $enrollment = $this->app->make(EnrollmentService::class)->enroll($owner->id, $course);

    $this->postJson(
        "api/courses/enrollments/{$enrollment->id}/lessons/{$lesson->id}/complete",
        ['user_id' => $other->id],
    )->assertForbidden();
});

// ── Self-enrolment is for free things ────────────────────────────────

test('a learner cannot self enrol in a priced course', function () {
    Course::factory()->create(['slug' => 'paid', 'price' => 29]);

    // A checkout in front of a course is worthless if the API beside it
    // enrols anyone who asks.
    $this->actingAs($this->learner())
        ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'paid'])
        ->assertForbidden();

    $this->assertDatabaseCount('enrollments', 0);
});

test('a learner can self enrol in a free course', function () {
    Course::factory()->create(['slug' => 'free', 'price' => null]);
    Course::factory()->create(['slug' => 'zero', 'price' => 0]);

    $user = $this->learner();
    foreach (['free', 'zero'] as $slug) {
        $this->actingAs($user)
            ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => $slug])
            ->assertSuccessful();
    }

    $this->assertDatabaseCount('enrollments', 2);
});

test('an existing enrollment in a priced course is returned not refused', function () {
    $user = $this->learner();
    $course = Course::factory()->create(['slug' => 'bought', 'price' => 29]);
    // Created server-side after payment, the way a host is meant to.
    $this->app->make(EnrollmentService::class)->enroll($user->id, $course);

    $this->actingAs($user)
        ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'bought'])
        ->assertSuccessful();
});

test('a host binding can admit priced enrolment', function () {
    $this->app->bind(GatesSelfEnrollment::class, fn () => new class implements GatesSelfEnrollment {
        public function allows(Request $request, int|string $userId, Curriculum|Course $target): bool
        {
            return true;
        }
    });
    Course::factory()->create(['slug' => 'members', 'price' => 29]);

    $this->actingAs($this->learner())
        ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'members'])
        ->assertSuccessful();
});
