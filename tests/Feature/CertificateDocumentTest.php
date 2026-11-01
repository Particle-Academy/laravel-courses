<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature;

use FancyTemplate\Template;
use FancyTemplate\TemplateError;
use FinePrint\Agent;
use InvalidArgumentException;
use ParticleAcademy\LaravelCourses\Certificates\CertificateDocument;
use PHPUnit\Framework\TestCase;

/**
 * The certificate builder is pure: template + scalar data in, fine-print
 * document out. No container, no database, so it is tested without either.
 */
class CertificateDocumentTest extends TestCase
{
    /** @return array<string,mixed> */
    private function data(array $overrides = []): array
    {
        return $overrides + [
            'title'             => 'Certificate of Completion',
            'recipientName'     => 'Ada Lovelace',
            'programName'       => 'Introduction to Analytical Engines',
            'programDetail'     => 'course',
            'issuedAt'          => '2026-10-27',
            'issuer'            => 'Particle Academy',
            'verificationCode'  => 'A1B2C3D4E5F60718',
            'certificateNumber' => 'CERT-2026-ABC123',
        ];
    }

    /** Every run's text, in document order, flattened through table cells. */
    private function texts(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            foreach ($block['runs'] ?? [] as $run) {
                $out[] = $run['text'];
            }
            foreach ($block['rows'] ?? [] as $row) {
                foreach ($row['cells'] as $cell) {
                    array_push($out, ...$this->texts($cell['blocks']));
                }
            }
        }

        return $out;
    }

    public function test_the_packaged_template_inspects_clean(): void
    {
        $this->assertSame(['ok' => true, 'issues' => []], Template::inspect(CertificateDocument::defaultTemplate()));
    }

    public function test_it_builds_the_framed_landscape_certificate_from_sample_data(): void
    {
        $doc = (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $this->data());

        $this->assertSame('Certificate of Completion', $doc['title']);
        $this->assertSame('letter', $doc['page']['size']);
        $this->assertSame('landscape', $doc['page']['orientation']);
        $this->assertSame('#FDFAF3', $doc['page']['background']);
        $this->assertSame(['style' => 'double', 'width' => 6, 'color' => '#B08D57', 'inset' => 24], $doc['page']['border']);

        $this->assertSame([
            'CERTIFICATE OF COMPLETION',
            'Certificate of Completion',
            'This is to certify that',
            'Ada Lovelace',
            'has successfully completed ',
            'Introduction to Analytical Engines',
            ', course.',
            'Issued',
            'October 27, 2026',
            'Issued by',
            'Particle Academy',
            'Verification',
            'A1B2C3D4E5F60718',
            'Certificate No. CERT-2026-ABC123',
        ], $this->texts($doc['blocks']));

        // What fine-print will accept and lay out without a finding.
        $this->assertSame([], Agent::validate($doc));
        $this->assertSame([], Agent::lint($doc));
    }

    public function test_the_certificate_number_line_is_dropped_when_there_is_none(): void
    {
        $data = $this->data();
        unset($data['certificateNumber']);

        $doc = (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $data);

        $this->assertNotContains('Certificate No. CERT-2026-ABC123', $this->texts($doc['blocks']));
    }

    public function test_paper_and_orientation_can_be_overridden(): void
    {
        $doc = (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $this->data(), 'A4', 'portrait');

        $this->assertSame('a4', $doc['page']['size']);
        $this->assertSame('portrait', $doc['page']['orientation']);
    }

    public function test_a_paper_size_fine_print_cannot_draw_is_refused_by_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tabloid');

        (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $this->data(), 'tabloid');
    }

    public function test_missing_required_data_is_loud(): void
    {
        $data = $this->data();
        unset($data['recipientName']);

        $this->expectException(TemplateError::class);

        (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $data);
    }

    public function test_the_default_fonts_are_wide_coverage_truetype_files_that_exist(): void
    {
        $fonts = CertificateDocument::fonts();

        $this->assertSame(['Times', 'Courier'], array_keys($fonts));
        foreach ($fonts as $faces) {
            $this->assertSame(['regular', 'bold', 'italic', 'boldItalic'], array_keys($faces));
            foreach ($faces as $path) {
                $this->assertFileExists($path);
            }
        }
    }
}
