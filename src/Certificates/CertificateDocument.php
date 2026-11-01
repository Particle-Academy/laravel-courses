<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Certificates;

use FancyTemplate\Template;
use FancyTemplate\TemplateError;
use FinePrint\Render\Glyphs\StandardFonts;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds a certificate as a fine-print document: a fancy-template envelope
 * plus scalar data in, plain fine-print JSON out.
 *
 * Pure on purpose: no container, no models, no I/O beyond reading the
 * packaged template. The service decides WHICH template and WHICH data; this
 * only turns them into a document, so it can be tested without either.
 */
final class CertificateDocument
{
    /** The page sizes fine-print draws. dompdf took many more. */
    public const PAPER = ['letter', 'legal', 'a4'];

    public const ORIENTATION = ['portrait', 'landscape'];

    public const DEFAULT_TEMPLATE = __DIR__ . '/../../resources/certificates/default.fine-print.json';

    /**
     * @param  array<string,mixed>  $template  a fancy-template envelope for the fine-print writer
     * @param  array<string,mixed>  $data      scalars only; never a model
     * @return array<string,mixed> a fine-print document
     *
     * @throws TemplateError when the template is broken or required data is missing
     * @throws InvalidArgumentException for a paper size or orientation fine-print cannot draw
     */
    public function build(array $template, array $data, ?string $paper = null, ?string $orientation = null): array
    {
        if (($template['writer'] ?? null) !== 'fine-print') {
            throw new InvalidArgumentException('A certificate template must target the fine-print writer.');
        }

        $doc = Template::render($template, $data);

        if ($paper !== null) {
            $doc['page']['size'] = self::choose('paper size', $paper, self::PAPER);
        }
        if ($orientation !== null) {
            $doc['page']['orientation'] = self::choose('orientation', $orientation, self::ORIENTATION);
        }

        return $doc;
    }

    /**
     * The packaged default template, or a host's replacement read from a path.
     *
     * @return array<string,mixed>
     */
    public static function defaultTemplate(?string $path = null): array
    {
        $path ??= self::DEFAULT_TEMPLATE;
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Certificate template not readable: {$path}");
        }

        $template = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($template)) {
            throw new RuntimeException("Certificate template is not a JSON object: {$path}");
        }

        return $template;
    }

    /**
     * fine-print's `fonts` option: the metric-compatible TrueType faces
     * fine-print itself bundles (Tinos for Times, Cousine for Courier),
     * registered under the standard names the template uses.
     *
     * Registering them embeds a subset, which is what lets a learner's name in
     * Polish, Greek or Cyrillic print. The standard 14 are Windows-1252 only
     * and would print "?" instead.
     *
     * @return array<string, array<string,string>>
     */
    public static function fonts(): array
    {
        $faces = static fn (array $names): array => array_combine(
            ['regular', 'bold', 'italic', 'boldItalic'],
            array_map(static fn (string $face): string => StandardFonts::file($face), $names),
        );

        return [
            'Times'   => $faces(['Times-Roman', 'Times-Bold', 'Times-Italic', 'Times-BoldItalic']),
            'Courier' => $faces(['Courier', 'Courier-Bold', 'Courier-Oblique', 'Courier-BoldOblique']),
        ];
    }

    /** @param  list<string>  $allowed */
    private static function choose(string $what, string $value, array $allowed): string
    {
        $normal = strtolower(trim($value));
        if (! in_array($normal, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Certificate %s "%s" is not one fine-print can draw; use one of: %s.',
                $what,
                $value,
                implode(', ', $allowed),
            ));
        }

        return $normal;
    }
}
