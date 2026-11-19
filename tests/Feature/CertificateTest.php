<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\CertificateTest;

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

function certificates(): CertificateService {
    return app()->make(CertificateService::class);
}

function enrolment(): Enrollment {
    return app()->make(EnrollmentService::class)
        ->enroll(test()->learner()->id, Course::factory()->create());
}

test('issuing produces a verification code and number', function () {
    $certificate = certificates()->issue(enrolment());

    $this->assertNotEmpty($certificate->verification_code);
    $this->assertMatchesRegularExpression('/^CERT-\d{4}-[A-Z0-9]{6}$/', $certificate->certificate_number);
    $this->assertNotNull($certificate->issued_at);
});

test('the verification code and the number are different things', function () {
    $certificate = certificates()->issue(enrolment());

    // The number is the human-readable one people quote; the code is the
    // unguessable token in the verify URL. Collapsing them would make a
    // certificate verifiable by anyone who saw it printed.
    $this->assertNotSame($certificate->verification_code, $certificate->certificate_number);
});

test('issuing twice returns the same certificate', function () {
    Event::fake([CertificateIssued::class]);
    $enrollment = enrolment();

    $first  = certificates()->issue($enrollment);
    $second = certificates()->issue($enrollment->refresh());

    $this->assertSame($first->id, $second->id);
    $this->assertDatabaseCount('certificates', 1);
    Event::assertDispatchedTimes(CertificateIssued::class, 1);
});

test('verification codes are unique across certificates', function () {
    $codes = [];
    for ($i = 0; $i < 25; $i++) {
        $codes[] = certificates()->issue(enrolment())->verification_code;
    }

    $this->assertCount(25, array_unique($codes));
});

test('revoking marks the certificate and dispatches once', function () {
    Event::fake([CertificateRevoked::class]);
    $certificate = certificates()->issue(enrolment());

    $revoked = certificates()->revoke($certificate, 'Issued in error');

    $this->assertNotNull($revoked->revoked_at);
    $this->assertSame('Issued in error', $revoked->revocation_reason);

    certificates()->revoke($revoked);
    Event::assertDispatchedTimes(CertificateRevoked::class, 1);
});

test('verify finds a certificate by code or number', function () {
    $certificate = certificates()->issue(enrolment());

    $this->assertSame($certificate->id, certificates()->verify($certificate->verification_code)?->id);
    $this->assertSame($certificate->id, certificates()->verify($certificate->certificate_number)?->id);
    $this->assertNull(certificates()->verify('not-a-real-code'));
});

test('the public verify endpoint reports a revoked certificate as invalid', function () {
    $certificate = certificates()->issue(enrolment());

    $this->getJson("api/courses/verify/{$certificate->verification_code}")
        ->assertOk()
        ->assertJson(['valid' => true]);

    certificates()->revoke($certificate, 'Fraud');

    // The whole point of revocation: the public check has to change answer.
    $this->getJson("api/courses/verify/{$certificate->verification_code}")
        ->assertOk()
        ->assertJson(['valid' => false, 'revocation_reason' => 'Fraud']);
});

test('an unknown code verifies as invalid', function () {
    $this->getJson('api/courses/verify/made-up-code')
        ->assertNotFound()
        ->assertJson(['valid' => false]);
});

test('a learner cannot claim a certificate before finishing', function () {
    config()->set('laravel-courses.allow_input_user_id', true);

    $user = $this->learner();
    $course = Course::factory()->create();
    Lesson::factory()->create(['course_id' => $course->id]);
    $enrollment = $this->app->make(EnrollmentService::class)->enroll($user->id, $course);

    $this->postJson("api/courses/enrollments/{$enrollment->id}/certificate", ['user_id' => $user->id])
        ->assertStatus(409);

    $this->assertSame(0, \ParticleAcademy\LaravelCourses\Models\Certificate::query()->count());
});

test('a learner can claim a certificate once complete', function () {
    config()->set('laravel-courses.allow_input_user_id', true);

    $user = $this->learner();
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $enrollment = $this->app->make(EnrollmentService::class)->enroll($user->id, $course);
    $this->app->make(ProgressService::class)->markLessonComplete($enrollment, $lesson);

    $this->postJson("api/courses/enrollments/{$enrollment->id}/certificate", ['user_id' => $user->id])
        ->assertCreated();
});

test('rendering uses the linked template', function () {
    $template = CertificateTemplate::factory()->create([
        'html' => '<h1>{{ certificateNumber }} / {{ recipientName }}</h1>',
    ]);
    $certificate = certificates()->issue(enrolment(), $template);

    $html = certificates()->renderHtml($certificate);

    $this->assertStringContainsString($certificate->certificate_number, $html);
    $this->assertStringContainsString('Ada', $html);
});

test('a template can also reach through the model', function () {
    $template = CertificateTemplate::factory()->create([
        'html' => '<p>{{ certificate.verification_code }}</p>',
    ]);
    $certificate = certificates()->issue(enrolment(), $template);

    $this->assertStringContainsString(
        $certificate->verification_code,
        certificates()->renderHtml($certificate),
    );
});

/**
 * An inline template is text an admin stores through the API, and it was
 * resolved with data_get over the live Eloquent models — so any column or
 * loaded relation on the learner's user row could be printed into a PDF.
 */
test('an inline template cannot read the recipients secrets', function () {
    $user = $this->learner();
    $user->forceFill([
        'password'          => 'HASHED-PASSWORD-SENTINEL',
        'remember_token'    => 'REMEMBER-TOKEN-SENTINEL',
        'two_factor_secret' => 'TWO-FACTOR-SENTINEL',
    ])->save();

    $enrollment = $this->app->make(EnrollmentService::class)
        ->enroll($user->id, Course::factory()->create(['title' => 'COURSE-TITLE-SENTINEL']));

    $template = CertificateTemplate::factory()->create([
        'html' => '<p>[{{ recipient.password }}]</p>'
            . '<p>[{{ recipient.remember_token }}]</p>'
            . '<p>[{{ recipient.two_factor_secret }}]</p>'
            . '<p>[{{ enrollment.enrollable.title }}]</p>'
            . '<p>[{{ certificate.enrollment.user_id }}]</p>'
            . '<p>[{{ certificate.notes }}]</p>',
    ]);
    $certificate = certificates()->issue($enrollment, $template, notes: 'INTERNAL-NOTE-SENTINEL');

    $html = certificates()->renderHtml($certificate);

    $this->assertStringNotContainsString('HASHED-PASSWORD-SENTINEL', $html);
    $this->assertStringNotContainsString('REMEMBER-TOKEN-SENTINEL', $html);
    $this->assertStringNotContainsString('TWO-FACTOR-SENTINEL', $html);
    $this->assertStringNotContainsString('COURSE-TITLE-SENTINEL', $html);
    $this->assertStringNotContainsString('INTERNAL-NOTE-SENTINEL', $html);
    // Same fail-soft as an unknown placeholder: blank, not an error.
    $this->assertSame(6, substr_count($html, '[]'));
});

test('the allowlisted model paths still render', function () {
    $template = CertificateTemplate::factory()->create([
        'html' => '<p>{{ certificate.verification_code }}|{{ certificate.certificate_number }}'
            . '|{{ recipient.name }}|{{ recipient.email }}</p>',
    ]);
    $certificate = certificates()->issue(enrolment(), $template);

    $html = certificates()->renderHtml($certificate);

    $this->assertStringContainsString(
        "{$certificate->verification_code}|{$certificate->certificate_number}|Ada|ada@example.test",
        $html,
    );
});

test('the template allowlist is configurable', function () {
    config()->set('laravel-courses.certificates.template_attributes.recipient', ['name']);

    $template = CertificateTemplate::factory()->create([
        'html' => '<p>[{{ recipient.name }}][{{ recipient.email }}]</p>',
    ]);
    $certificate = certificates()->issue(enrolment(), $template);

    $this->assertStringContainsString('[Ada][]', certificates()->renderHtml($certificate));
});

test('an unknown placeholder renders empty rather than throwing', function () {
    $template = CertificateTemplate::factory()->create([
        'html' => '<p>[{{ noSuchVariable }}]</p>',
    ]);
    $certificate = certificates()->issue(enrolment(), $template);

    // Fail-soft is the right call for a certificate — a typo in a template
    // should not 500 at the moment someone tries to download theirs. It
    // does mean a typo shows up as a blank rather than an error, so this
    // documents the trade rather than leaving it implicit.
    $this->assertStringContainsString('[]', certificates()->renderHtml($certificate));
});

test('rendering falls back to the packaged view', function () {
    $certificate = certificates()->issue(enrolment());

    // No template row at all — the package's own Blade view has to carry
    // it, or a host that never configured one gets an exception at the
    // worst possible moment.
    $html = certificates()->renderHtml($certificate);

    $this->assertNotSame('', trim($html));
});
