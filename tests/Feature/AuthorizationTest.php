<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

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
class AuthorizationTest extends TestCase
{
    private function allowAdmin(): void
    {
        $this->app->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
            public function allows(Request $request): bool
            {
                return true;
            }
        });
    }

    // ── Writes are denied by default ─────────────────────────────────────

    public function test_an_anonymous_caller_cannot_create_content(): void
    {
        $this->postJson('api/courses/curriculums', ['slug' => 'pwned', 'title' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseMissing('curriculums', ['slug' => 'pwned']);
    }

    public function test_an_anonymous_caller_cannot_destroy_content(): void
    {
        $curriculum = Curriculum::factory()->create(['slug' => 'real-curriculum']);

        $this->deleteJson('api/courses/curriculums/real-curriculum')->assertForbidden();

        $this->assertTrue(Curriculum::whereKey($curriculum->id)->exists());
    }

    public function test_an_anonymous_caller_cannot_mint_a_certificate(): void
    {
        Course::factory()->create(['slug' => 'some-course', 'is_published' => true]);

        $this->postJson('api/courses/admin/completions', [
            'user_id'     => 4242,
            'target_kind' => 'course',
            'target_slug' => 'some-course',
        ])->assertForbidden();

        // The point of the whole package: no certificate exists for a learner
        // who did nothing.
        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_an_anonymous_caller_cannot_revoke_a_certificate(): void
    {
        $user = $this->learner();
        $course = Course::factory()->create();
        $enrollment = $this->app->make(EnrollmentService::class)->enroll($user->id, $course);
        $certificate = $this->app->make(\ParticleAcademy\LaravelCourses\Services\CertificateService::class)
            ->issue($enrollment);

        $this->postJson("api/courses/certificates/{$certificate->id}/revoke")->assertForbidden();

        $this->assertNull($certificate->refresh()->revoked_at);
    }

    public function test_every_write_route_is_gated(): void
    {
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
    }

    // ── Reads stay open ──────────────────────────────────────────────────

    public function test_reading_the_catalogue_stays_public(): void
    {
        Curriculum::factory()->create(['slug' => 'published-thing', 'is_published' => true]);

        $this->getJson('api/courses/curriculums')->assertOk();
        $this->getJson('api/courses/curriculums/published-thing')->assertOk();
    }

    public function test_certificate_verification_stays_public(): void
    {
        $user = $this->learner();
        $enrollment = $this->app->make(EnrollmentService::class)
            ->enroll($user->id, Course::factory()->create());
        $certificate = $this->app->make(\ParticleAcademy\LaravelCourses\Services\CertificateService::class)
            ->issue($enrollment);

        // A certificate nobody can check is worthless, so this one route is
        // deliberately open.
        $this->getJson("api/courses/verify/{$certificate->verification_code}")->assertOk();
    }

    // ── A bound authorizer switches authoring on ─────────────────────────

    public function test_a_host_binding_enables_authoring(): void
    {
        $this->allowAdmin();

        $this->postJson('api/courses/curriculums', [
            'slug' => 'allowed', 'title' => 'Allowed',
        ])->assertCreated();

        $this->assertDatabaseHas('curriculums', ['slug' => 'allowed']);
    }

    // ── Learner identity cannot be claimed ───────────────────────────────

    public function test_a_caller_cannot_claim_to_be_a_learner_by_default(): void
    {
        $resolver = $this->app->make(LearnerResolver::class);
        $request = Request::create('/', 'POST', ['user_id' => 999]);

        // Every ownership check in the package compares against this value.
        // If an anonymous caller can set it, those checks are decorative.
        $this->expectException(RuntimeException::class);
        $resolver->resolve($request);
    }

    public function test_a_caller_cannot_claim_to_be_a_learner_via_header(): void
    {
        $resolver = $this->app->make(LearnerResolver::class);
        $request = Request::create('/', 'POST');
        $request->headers->set('X-Learner-Id', '999');

        $this->expectException(RuntimeException::class);
        $resolver->resolve($request);
    }

    public function test_a_host_can_opt_into_trusting_a_supplied_learner_id(): void
    {
        config()->set('laravel-courses.allow_input_user_id', true);

        $resolver = $this->app->make(LearnerResolver::class);
        $this->assertSame(999, $resolver->resolve(Request::create('/', 'POST', ['user_id' => 999])));
    }

    public function test_an_authenticated_user_always_resolves_to_themselves(): void
    {
        config()->set('laravel-courses.allow_input_user_id', true);
        $user = $this->learner();

        $request = Request::create('/', 'POST', ['user_id' => 999]);
        $request->setUserResolver(fn () => $user);

        // Even with the fallback on, a real session wins over the input — so
        // an authenticated learner cannot act as someone else.
        $this->assertSame($user->id, $this->app->make(LearnerResolver::class)->resolve($request));
    }

    public function test_a_learner_cannot_complete_another_learners_lesson(): void
    {
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
    }

    // ── Self-enrolment is for free things ────────────────────────────────

    public function test_a_learner_cannot_self_enrol_in_a_priced_course(): void
    {
        Course::factory()->create(['slug' => 'paid', 'price' => 29]);

        // A checkout in front of a course is worthless if the API beside it
        // enrols anyone who asks.
        $this->actingAs($this->learner())
            ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'paid'])
            ->assertForbidden();

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_a_learner_can_self_enrol_in_a_free_course(): void
    {
        Course::factory()->create(['slug' => 'free', 'price' => null]);
        Course::factory()->create(['slug' => 'zero', 'price' => 0]);

        $user = $this->learner();
        foreach (['free', 'zero'] as $slug) {
            $this->actingAs($user)
                ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => $slug])
                ->assertSuccessful();
        }

        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_an_existing_enrollment_in_a_priced_course_is_returned_not_refused(): void
    {
        $user = $this->learner();
        $course = Course::factory()->create(['slug' => 'bought', 'price' => 29]);
        // Created server-side after payment, the way a host is meant to.
        $this->app->make(EnrollmentService::class)->enroll($user->id, $course);

        $this->actingAs($user)
            ->postJson('api/courses/enrollments', ['target_kind' => 'course', 'target_slug' => 'bought'])
            ->assertSuccessful();
    }

    public function test_a_host_binding_can_admit_priced_enrolment(): void
    {
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
    }
}
