<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\CertificatePdfTest;

use FinePrint\Agent;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use ParticleAcademy\LaravelCourses\Certificates\CertificateDocument;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Models\Certificate;
use ParticleAcademy\LaravelCourses\Models\CertificateTemplate;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Tests\TestCase;

/**
 * The PDF a learner downloads, written by fine-print and read back by
 * fine-print's own reader: the assertions are about what is IN the file, not
 * about which code path ran.
 */

function certificates(): CertificateService {
    return app()->make(CertificateService::class);
}

function certificate(string $name = 'Ada Lovelace', ?CertificateTemplate $template = null): Certificate {
    config()->set('app.name', 'Particle Academy');

    $enrollment = app()->make(EnrollmentService::class)->enroll(
        test()->learner($name)->id,
        Course::factory()->create(['title' => 'Introduction to Analytical Engines']),
    );
    $certificate = certificates()->issue($enrollment, $template);
    // Both columns are unique; the first certificate in a test gets the
    // fixed sample values the assertions name, any later one a suffix.
    $suffix = Certificate::query()->count() > 1 ? '-' . $certificate->id : '';
    $certificate->forceFill([
        'verification_code'  => 'A1B2C3D4E5F60718' . $suffix,
        'certificate_number' => 'CERT-2026-ABC123' . $suffix,
        'issued_at'          => '2026-10-27 12:00:00',
    ])->save();

    return $certificate->fresh();
}

/** @return array<string,mixed> fine-print's guarded read of the bytes */
function readBack(string $pdf): array {
    test()->assertStringStartsWith('%PDF-', $pdf);
    $read = Agent::read($pdf);
    test()->assertTrue($read['ok'], 'fine-print could not read the certificate back');

    return $read;
}

function allowAdmin(): void {
    app()->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
        public function allows(Request $request): bool
        {
            return true;
        }
    });
}

test('template data is scalars only', function () {
    $data = certificates()->templateData(certificate());

    $this->assertSame([
        'title'             => 'Certificate of Completion',
        'recipientName'     => 'Ada Lovelace',
        'programName'       => 'Introduction to Analytical Engines',
        'programDetail'     => 'course',
        'issuedAt'          => '2026-10-27',
        'issuer'            => 'Particle Academy',
        'verificationCode'  => 'A1B2C3D4E5F60718',
        'certificateNumber' => 'CERT-2026-ABC123',
    ], $data);
});

test('the pdf carries the learner the program and the date', function () {
    $read = readBack(certificates()->pdf(certificate()));

    $this->assertSame(1, $read['pageCount']);
    $text = $read['pages'][0]['text'];
    foreach ([
        'Ada Lovelace',
        'Introduction to Analytical Engines',
        'October 27, 2026',
        'Particle Academy',
        'A1B2C3D4E5F60718',
        'Certificate No. CERT-2026-ABC123',
    ] as $expected) {
        $this->assertStringContainsString($expected, $text, "missing from the PDF: {$expected}");
    }
});

test('the pdf has no active content', function () {
    $pdf = certificates()->pdf(certificate());

    $read = readBack($pdf);
    $this->assertSame([], $read['activeContent']);
    $this->assertSame([], $read['guard']['findings']);

    foreach (['/JavaScript', '/JS', '/OpenAction', '/AA', '/Launch', '/EmbeddedFile', '/AcroForm', '/XFA', '/RichMedia'] as $name) {
        $this->assertDoesNotMatchRegularExpression('#' . preg_quote($name, '#') . '(?![A-Za-z])#', $pdf, "{$name} written into the certificate");
    }
});

test('the page is letter landscape by default and follows the config', function () {
    $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+792\s+612\s*\]#', certificates()->pdf(certificate()));

    config()->set('laravel-courses.certificates.paper', 'a4');
    config()->set('laravel-courses.certificates.orientation', 'portrait');
    $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+595\.28\s+841\.89\s*\]#', certificates()->pdf(certificate('Grace Hopper')));
});

/**
 * Standard-14 fonts are Windows-1252 only and print "?" for anything else,
 * which on a certificate means a learner's own name. The default fonts are
 * embedded TrueType, so these read back exactly.
 */
test('a name outside windows 1252 prints exactly', function () {
    foreach (['Łukasz Żółkiewski', 'Анна Каренина', 'Ελένη Παπαδοπούλου'] as $name) {
        $text = readBack(certificates()->pdf(certificate($name)))['pages'][0]['text'];

        $this->assertStringContainsString($name, $text);
    }
});

test('a template document drives the pdf', function () {
    $template = CertificateTemplate::factory()->create([
        'document' => [
            'fancyTemplate' => 1,
            'writer'        => 'fine-print',
            'variables'     => [
                'recipientName' => ['type' => 'string', 'required' => true],
                'programName'   => ['type' => 'string', 'required' => true],
            ],
            'document' => [
                'page'   => ['size' => 'a4', 'orientation' => 'portrait'],
                'blocks' => [[
                    'type' => 'paragraph',
                    'runs' => [['text' => 'CUSTOM {{ recipientName }} finished {{ programName }}']],
                ]],
            ],
        ],
    ]);

    $pdf = certificates()->pdf(certificate(template: $template));

    $this->assertStringContainsString(
        'CUSTOM Ada Lovelace finished Introduction to Analytical Engines',
        readBack($pdf)['pages'][0]['text'],
    );
    // The template owns its page; the paper config is for the packaged default.
    $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+595\.28\s+841\.89\s*\]#', $pdf);
});

test('an html only template falls back to the packaged certificate and says so', function () {
    // Counted off the framework's own MessageLogged event, not a Mockery spy.
    $warnings = 0;
    Event::listen(MessageLogged::class, function (MessageLogged $logged) use (&$warnings): void {
        if ($logged->level === 'warning') {
            $warnings++;
        }
    });
    $template = CertificateTemplate::factory()->create(['html' => '<h1>{{ recipientName }}</h1>']);

    $text = readBack(certificates()->pdf(certificate(template: $template)))['pages'][0]['text'];

    $this->assertStringContainsString('This is to certify that', $text);
    $this->assertStringContainsString('Ada Lovelace', $text);
    $this->assertSame(1, $warnings);
});

test('the packaged template can be replaced by config', function () {
    $path = tempnam(sys_get_temp_dir(), 'cert') . '.json';
    $template = CertificateDocument::defaultTemplate();
    $template['document']['blocks'][2]['runs'][0]['text'] = 'HOST WORDING for';
    file_put_contents($path, json_encode($template));
    config()->set('laravel-courses.certificates.default_template', $path);

    try {
        $text = readBack(certificates()->pdf(certificate()))['pages'][0]['text'];
    } finally {
        @unlink($path);
    }

    $this->assertStringContainsString('HOST WORDING for', $text);
});

test('the download route still serves a pdf', function () {
    config()->set('laravel-courses.allow_input_user_id', true);
    $certificate = certificate();

    $response = $this->get("api/courses/certificates/{$certificate->id}/pdf?user_id={$certificate->enrollment->user_id}");

    $response->assertOk();
    $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    $this->assertStringContainsString('Ada Lovelace', readBack((string) $response->getContent())['pages'][0]['text']);
});

test('the admin api refuses a broken template document with its paths', function () {
    allowAdmin();

    $this->postJson('api/courses/certificate-templates', [
        'slug'     => 'broken',
        'name'     => 'Broken',
        'document' => [
            'fancyTemplate' => 1,
            'writer'        => 'fine-print',
            'variables'     => [],
            'document'      => ['blocks' => [['type' => 'paragraph', 'runs' => [['text' => '{{ undeclared }}']]]]],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('document');

    $this->assertSame(0, CertificateTemplate::query()->count());
});

test('the admin api refuses a document for another writer', function () {
    allowAdmin();
    $template = CertificateDocument::defaultTemplate();
    $template['writer'] = 'last-word';

    $this->postJson('api/courses/certificate-templates', ['slug' => 'docx', 'name' => 'Docx', 'document' => $template])
        ->assertUnprocessable()->assertJsonValidationErrors('document');
});

test('the admin api stores and returns a valid template document', function () {
    allowAdmin();

    $this->postJson('api/courses/certificate-templates', [
        'slug'     => 'house',
        'name'     => 'House style',
        'document' => CertificateDocument::defaultTemplate(),
    ])->assertCreated()->assertJsonPath('data.document.writer', 'fine-print');

    $this->assertSame('fine-print', CertificateTemplate::query()->firstOrFail()->document['writer']);
});
