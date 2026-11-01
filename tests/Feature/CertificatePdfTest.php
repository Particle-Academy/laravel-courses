<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

use FinePrint\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
class CertificatePdfTest extends TestCase
{
    private function certificates(): CertificateService
    {
        return $this->app->make(CertificateService::class);
    }

    private function certificate(string $name = 'Ada Lovelace', ?CertificateTemplate $template = null): Certificate
    {
        config()->set('app.name', 'Particle Academy');

        $enrollment = $this->app->make(EnrollmentService::class)->enroll(
            $this->learner($name)->id,
            Course::factory()->create(['title' => 'Introduction to Analytical Engines']),
        );
        $certificate = $this->certificates()->issue($enrollment, $template);
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
    private function readBack(string $pdf): array
    {
        $this->assertStringStartsWith('%PDF-', $pdf);
        $read = Agent::read($pdf);
        $this->assertTrue($read['ok'], 'fine-print could not read the certificate back');

        return $read;
    }

    private function allowAdmin(): void
    {
        $this->app->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
            public function allows(Request $request): bool
            {
                return true;
            }
        });
    }

    public function test_template_data_is_scalars_only(): void
    {
        $data = $this->certificates()->templateData($this->certificate());

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
    }

    public function test_the_pdf_carries_the_learner_the_program_and_the_date(): void
    {
        $read = $this->readBack($this->certificates()->pdf($this->certificate()));

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
    }

    public function test_the_pdf_has_no_active_content(): void
    {
        $pdf = $this->certificates()->pdf($this->certificate());

        $read = $this->readBack($pdf);
        $this->assertSame([], $read['activeContent']);
        $this->assertSame([], $read['guard']['findings']);

        foreach (['/JavaScript', '/JS', '/OpenAction', '/AA', '/Launch', '/EmbeddedFile', '/AcroForm', '/XFA', '/RichMedia'] as $name) {
            $this->assertDoesNotMatchRegularExpression('#' . preg_quote($name, '#') . '(?![A-Za-z])#', $pdf, "{$name} written into the certificate");
        }
    }

    public function test_the_page_is_letter_landscape_by_default_and_follows_the_config(): void
    {
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+792\s+612\s*\]#', $this->certificates()->pdf($this->certificate()));

        config()->set('laravel-courses.certificates.paper', 'a4');
        config()->set('laravel-courses.certificates.orientation', 'portrait');
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+595\.28\s+841\.89\s*\]#', $this->certificates()->pdf($this->certificate('Grace Hopper')));
    }

    /**
     * Standard-14 fonts are Windows-1252 only and print "?" for anything else,
     * which on a certificate means a learner's own name. The default fonts are
     * embedded TrueType, so these read back exactly.
     */
    public function test_a_name_outside_windows_1252_prints_exactly(): void
    {
        foreach (['Łukasz Żółkiewski', 'Анна Каренина', 'Ελένη Παπαδοπούλου'] as $name) {
            $text = $this->readBack($this->certificates()->pdf($this->certificate($name)))['pages'][0]['text'];

            $this->assertStringContainsString($name, $text);
        }
    }

    public function test_a_template_document_drives_the_pdf(): void
    {
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

        $pdf = $this->certificates()->pdf($this->certificate(template: $template));

        $this->assertStringContainsString(
            'CUSTOM Ada Lovelace finished Introduction to Analytical Engines',
            $this->readBack($pdf)['pages'][0]['text'],
        );
        // The template owns its page; the paper config is for the packaged default.
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[\s*0\s+0\s+595\.28\s+841\.89\s*\]#', $pdf);
    }

    public function test_an_html_only_template_falls_back_to_the_packaged_certificate_and_says_so(): void
    {
        Log::spy();
        $template = CertificateTemplate::factory()->create(['html' => '<h1>{{ recipientName }}</h1>']);

        $text = $this->readBack($this->certificates()->pdf($this->certificate(template: $template)))['pages'][0]['text'];

        $this->assertStringContainsString('This is to certify that', $text);
        $this->assertStringContainsString('Ada Lovelace', $text);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_the_packaged_template_can_be_replaced_by_config(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cert') . '.json';
        $template = CertificateDocument::defaultTemplate();
        $template['document']['blocks'][2]['runs'][0]['text'] = 'HOST WORDING for';
        file_put_contents($path, json_encode($template));
        config()->set('laravel-courses.certificates.default_template', $path);

        try {
            $text = $this->readBack($this->certificates()->pdf($this->certificate()))['pages'][0]['text'];
        } finally {
            @unlink($path);
        }

        $this->assertStringContainsString('HOST WORDING for', $text);
    }

    public function test_the_download_route_still_serves_a_pdf(): void
    {
        config()->set('laravel-courses.allow_input_user_id', true);
        $certificate = $this->certificate();

        $response = $this->get("api/courses/certificates/{$certificate->id}/pdf?user_id={$certificate->enrollment->user_id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Ada Lovelace', $this->readBack((string) $response->getContent())['pages'][0]['text']);
    }

    public function test_the_admin_api_refuses_a_broken_template_document_with_its_paths(): void
    {
        $this->allowAdmin();

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
    }

    public function test_the_admin_api_refuses_a_document_for_another_writer(): void
    {
        $this->allowAdmin();
        $template = CertificateDocument::defaultTemplate();
        $template['writer'] = 'last-word';

        $this->postJson('api/courses/certificate-templates', ['slug' => 'docx', 'name' => 'Docx', 'document' => $template])
            ->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_the_admin_api_stores_and_returns_a_valid_template_document(): void
    {
        $this->allowAdmin();

        $this->postJson('api/courses/certificate-templates', [
            'slug'     => 'house',
            'name'     => 'House style',
            'document' => CertificateDocument::defaultTemplate(),
        ])->assertCreated()->assertJsonPath('data.document.writer', 'fine-print');

        $this->assertSame('fine-print', CertificateTemplate::query()->firstOrFail()->document['writer']);
    }
}
