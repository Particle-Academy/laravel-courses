<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

use Illuminate\Support\Facades\Event;
use ParticleAcademy\LaravelCourses\Events\CertificateIssued;
use ParticleAcademy\LaravelCourses\Events\CertificateRevoked;
use ParticleAcademy\LaravelCourses\Models\CertificateTemplate;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;

class CertificateTest extends TestCase
{
    private function certificates(): CertificateService
    {
        return $this->app->make(CertificateService::class);
    }

    private function enrolment(): Enrollment
    {
        return $this->app->make(EnrollmentService::class)
            ->enroll($this->learner()->id, Course::factory()->create());
    }

    public function test_issuing_produces_a_verification_code_and_number(): void
    {
        $certificate = $this->certificates()->issue($this->enrolment());

        $this->assertNotEmpty($certificate->verification_code);
        $this->assertMatchesRegularExpression('/^CERT-\d{4}-[A-Z0-9]{6}$/', $certificate->certificate_number);
        $this->assertNotNull($certificate->issued_at);
    }

    public function test_the_verification_code_and_the_number_are_different_things(): void
    {
        $certificate = $this->certificates()->issue($this->enrolment());

        // The number is the human-readable one people quote; the code is the
        // unguessable token in the verify URL. Collapsing them would make a
        // certificate verifiable by anyone who saw it printed.
        $this->assertNotSame($certificate->verification_code, $certificate->certificate_number);
    }

    public function test_issuing_twice_returns_the_same_certificate(): void
    {
        Event::fake([CertificateIssued::class]);
        $enrollment = $this->enrolment();

        $first  = $this->certificates()->issue($enrollment);
        $second = $this->certificates()->issue($enrollment->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('certificates', 1);
        Event::assertDispatchedTimes(CertificateIssued::class, 1);
    }

    public function test_verification_codes_are_unique_across_certificates(): void
    {
        $codes = [];
        for ($i = 0; $i < 25; $i++) {
            $codes[] = $this->certificates()->issue($this->enrolment())->verification_code;
        }

        $this->assertCount(25, array_unique($codes));
    }

    public function test_revoking_marks_the_certificate_and_dispatches_once(): void
    {
        Event::fake([CertificateRevoked::class]);
        $certificate = $this->certificates()->issue($this->enrolment());

        $revoked = $this->certificates()->revoke($certificate, 'Issued in error');

        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame('Issued in error', $revoked->revocation_reason);

        $this->certificates()->revoke($revoked);
        Event::assertDispatchedTimes(CertificateRevoked::class, 1);
    }

    public function test_verify_finds_a_certificate_by_code_or_number(): void
    {
        $certificate = $this->certificates()->issue($this->enrolment());

        $this->assertSame($certificate->id, $this->certificates()->verify($certificate->verification_code)?->id);
        $this->assertSame($certificate->id, $this->certificates()->verify($certificate->certificate_number)?->id);
        $this->assertNull($this->certificates()->verify('not-a-real-code'));
    }

    public function test_the_public_verify_endpoint_reports_a_revoked_certificate_as_invalid(): void
    {
        $certificate = $this->certificates()->issue($this->enrolment());

        $this->getJson("api/courses/verify/{$certificate->verification_code}")
            ->assertOk()
            ->assertJson(['valid' => true]);

        $this->certificates()->revoke($certificate, 'Fraud');

        // The whole point of revocation: the public check has to change answer.
        $this->getJson("api/courses/verify/{$certificate->verification_code}")
            ->assertOk()
            ->assertJson(['valid' => false, 'revocation_reason' => 'Fraud']);
    }

    public function test_an_unknown_code_verifies_as_invalid(): void
    {
        $this->getJson('api/courses/verify/made-up-code')
            ->assertNotFound()
            ->assertJson(['valid' => false]);
    }

    public function test_a_learner_cannot_claim_a_certificate_before_finishing(): void
    {
        config()->set('laravel-courses.allow_input_user_id', true);

        $user = $this->learner();
        $course = Course::factory()->create();
        Lesson::factory()->create(['course_id' => $course->id]);
        $enrollment = $this->app->make(EnrollmentService::class)->enroll($user->id, $course);

        $this->postJson("api/courses/enrollments/{$enrollment->id}/certificate", ['user_id' => $user->id])
            ->assertStatus(409);

        $this->assertSame(0, \ParticleAcademy\LaravelCourses\Models\Certificate::query()->count());
    }

    public function test_a_learner_can_claim_a_certificate_once_complete(): void
    {
        config()->set('laravel-courses.allow_input_user_id', true);

        $user = $this->learner();
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $enrollment = $this->app->make(EnrollmentService::class)->enroll($user->id, $course);
        $this->app->make(ProgressService::class)->markLessonComplete($enrollment, $lesson);

        $this->postJson("api/courses/enrollments/{$enrollment->id}/certificate", ['user_id' => $user->id])
            ->assertCreated();
    }

    public function test_rendering_uses_the_linked_template(): void
    {
        $template = CertificateTemplate::factory()->create([
            'html' => '<h1>{{ certificateNumber }} / {{ recipientName }}</h1>',
        ]);
        $certificate = $this->certificates()->issue($this->enrolment(), $template);

        $html = $this->certificates()->renderHtml($certificate);

        $this->assertStringContainsString($certificate->certificate_number, $html);
        $this->assertStringContainsString('Ada', $html);
    }

    public function test_a_template_can_also_reach_through_the_model(): void
    {
        $template = CertificateTemplate::factory()->create([
            'html' => '<p>{{ certificate.verification_code }}</p>',
        ]);
        $certificate = $this->certificates()->issue($this->enrolment(), $template);

        $this->assertStringContainsString(
            $certificate->verification_code,
            $this->certificates()->renderHtml($certificate),
        );
    }

    public function test_an_unknown_placeholder_renders_empty_rather_than_throwing(): void
    {
        $template = CertificateTemplate::factory()->create([
            'html' => '<p>[{{ noSuchVariable }}]</p>',
        ]);
        $certificate = $this->certificates()->issue($this->enrolment(), $template);

        // Fail-soft is the right call for a certificate — a typo in a template
        // should not 500 at the moment someone tries to download theirs. It
        // does mean a typo shows up as a blank rather than an error, so this
        // documents the trade rather than leaving it implicit.
        $this->assertStringContainsString('[]', $this->certificates()->renderHtml($certificate));
    }

    public function test_rendering_falls_back_to_the_packaged_view(): void
    {
        $certificate = $this->certificates()->issue($this->enrolment());

        // No template row at all — the package's own Blade view has to carry
        // it, or a host that never configured one gets an exception at the
        // worst possible moment.
        $html = $this->certificates()->renderHtml($certificate);

        $this->assertNotSame('', trim($html));
    }
}
