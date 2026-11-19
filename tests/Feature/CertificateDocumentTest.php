<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\CertificateDocumentTest;

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

/** @return array<string,mixed> */
function data(array $overrides = []): array {
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
function texts(array $blocks): array {
    $out = [];
    foreach ($blocks as $block) {
        foreach ($block['runs'] ?? [] as $run) {
            $out[] = $run['text'];
        }
        foreach ($block['rows'] ?? [] as $row) {
            foreach ($row['cells'] as $cell) {
                array_push($out, ...texts($cell['blocks']));
            }
        }
    }

    return $out;
}

test('the packaged template inspects clean', function () {
    $this->assertSame(['ok' => true, 'issues' => []], Template::inspect(CertificateDocument::defaultTemplate()));
});

test('it builds the framed landscape certificate from sample data', function () {
    $doc = (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), data());

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
    ], texts($doc['blocks']));

    // What fine-print will accept and lay out without a finding.
    $this->assertSame([], Agent::validate($doc));
    $this->assertSame([], Agent::lint($doc));
});

test('the certificate number line is dropped when there is none', function () {
    $data = data();
    unset($data['certificateNumber']);

    $doc = (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $data);

    $this->assertNotContains('Certificate No. CERT-2026-ABC123', texts($doc['blocks']));
});

test('paper and orientation can be overridden', function () {
    $doc = (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), data(), 'A4', 'portrait');

    $this->assertSame('a4', $doc['page']['size']);
    $this->assertSame('portrait', $doc['page']['orientation']);
});

test('a paper size fine print cannot draw is refused by name', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('tabloid');

    (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), data(), 'tabloid');
});

test('missing required data is loud', function () {
    $data = data();
    unset($data['recipientName']);

    $this->expectException(TemplateError::class);

    (new CertificateDocument())->build(CertificateDocument::defaultTemplate(), $data);
});

test('the default fonts are wide coverage truetype files that exist', function () {
    $fonts = CertificateDocument::fonts();

    $this->assertSame(['Times', 'Courier'], array_keys($fonts));
    foreach ($fonts as $faces) {
        $this->assertSame(['regular', 'bold', 'italic', 'boldItalic'], array_keys($faces));
        foreach ($faces as $path) {
            $this->assertFileExists($path);
        }
    }
});
