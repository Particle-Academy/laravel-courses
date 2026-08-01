<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\FilesystemManager;
use ParticleAcademy\LaravelCourses\Events\CertificateIssued;
use ParticleAcademy\LaravelCourses\Events\CertificateRevoked;
use ParticleAcademy\LaravelCourses\Models\Certificate;
use ParticleAcademy\LaravelCourses\Models\CertificateTemplate;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use RuntimeException;

class CertificateService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ViewFactory $views,
        private readonly FilesystemManager $filesystems,
    ) {
    }

    /**
     * @param  array<string,mixed>  $metadata
     */
    public function issue(
        Enrollment $enrollment,
        ?CertificateTemplate $template = null,
        array $metadata = [],
        int|string|null $issuedByUserId = null,
        ?string $notes = null,
    ): Certificate {
        $template ??= $this->resolveTemplate($enrollment);

        $existing = $enrollment->certificate;
        if ($existing) {
            return $existing;
        }

        $verificationCode = $this->generateVerificationCode();
        $certificateNumber = $this->generateCertificateNumber();

        $certificate = Certificate::create([
            'enrollment_id'           => $enrollment->getKey(),
            'certificate_template_id' => $template?->getKey(),
            'verification_code'       => $verificationCode,
            'certificate_number'      => $certificateNumber,
            'issued_by_user_id'       => $issuedByUserId,
            'issued_at'               => now(),
            'notes'                   => $notes,
            'metadata'                => $metadata ?: null,
        ]);

        CertificateIssued::dispatch($certificate);

        return $certificate;
    }

    public function revoke(Certificate $certificate, ?string $reason = null): Certificate
    {
        if ($certificate->isRevoked()) {
            return $certificate;
        }

        $certificate->forceFill([
            'revoked_at'        => now(),
            'revocation_reason' => $reason,
        ])->save();

        $fresh = $certificate->refresh();
        CertificateRevoked::dispatch($fresh, $reason);

        return $fresh;
    }

    public function verify(string $codeOrNumber): ?Certificate
    {
        return Certificate::query()
            ->where('verification_code', $codeOrNumber)
            ->orWhere('certificate_number', $codeOrNumber)
            ->first();
    }

    /**
     * Render certificate HTML using the linked (or default) template.
     */
    public function renderHtml(Certificate $certificate): string
    {
        $variables = $this->buildVariables($certificate);
        $template  = $certificate->template;

        if ($template?->html) {
            return $this->renderInlineTemplate($template->html, $template->css, $variables);
        }

        $view = $template?->blade_view ?? config('laravel-courses.certificates.default_view');

        return $this->views->make($view, $variables)->render();
    }

    /**
     * Render certificate as a PDF binary string.
     */
    public function pdf(Certificate $certificate): string
    {
        $html = $this->renderHtml($certificate);

        return Pdf::loadHTML($html)
            ->setPaper(
                config('laravel-courses.certificates.paper', 'letter'),
                config('laravel-courses.certificates.orientation', 'landscape'),
            )
            ->output();
    }

    /**
     * Generate a PDF, persist it to the configured disk, and return the path.
     */
    public function storePdf(Certificate $certificate): string
    {
        $disk = $this->filesystems->disk(
            config('laravel-courses.certificates.storage_disk', 'local'),
        );

        $path = sprintf(
            '%s/%s.pdf',
            rtrim((string) config('laravel-courses.certificates.storage_path', 'certificates'), '/'),
            $certificate->verification_code,
        );

        $disk->put($path, $this->pdf($certificate));

        $certificate->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    /**
     * Variables made available to the certificate template.
     *
     * @return array<string,mixed>
     */
    public function buildVariables(Certificate $certificate): array
    {
        $enrollment = $certificate->enrollment()->with('enrollable')->firstOrFail();
        $user       = $this->enrollments->userFor($enrollment);

        $target = $enrollment->enrollable;
        $programName   = $target?->title ?? 'Program';
        $programDetail = match (true) {
            $target instanceof Curriculum => 'curriculum',
            $target instanceof Course     => 'course',
            default                       => null,
        };

        return [
            'certificate'      => $certificate,
            'enrollment'       => $enrollment,
            'recipient'        => $user,
            'recipientName'    => $this->recipientName($user),
            'title'            => 'Certificate of Completion',
            'programName'      => $programName,
            'programDetail'    => $programDetail,
            'issuedAt'         => $certificate->issued_at?->format('F j, Y') ?? '',
            'issuer'           => config('app.name'),
            'verificationCode' => $certificate->verification_code,
            // The number is what a holder quotes and an employer types in, so
            // it belongs alongside the code rather than only reachable by
            // walking the model.
            'certificateNumber' => $certificate->certificate_number,
        ];
    }

    private function resolveTemplate(Enrollment $enrollment): ?CertificateTemplate
    {
        $target = $enrollment->enrollable;

        if ($target instanceof Course || $target instanceof Curriculum) {
            if ($target->certificate_template_id) {
                return $target->certificateTemplate;
            }
        }

        return CertificateTemplate::default();
    }

    /**
     * @param  array<string,mixed>  $variables
     */
    private function renderInlineTemplate(string $html, ?string $css, array $variables): string
    {
        $body = preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            function (array $m) use ($variables): string {
                $value = data_get($variables, $m[1], '');

                return is_scalar($value) ? e((string) $value) : '';
            },
            $html,
        ) ?? $html;

        $style = $css ? "<style>{$css}</style>" : '';

        return "<!DOCTYPE html><html><head><meta charset=\"utf-8\">{$style}</head><body>{$body}</body></html>";
    }

    private function recipientName(mixed $user): string
    {
        if ($user === null) {
            return 'Anonymous Learner';
        }

        foreach (['name', 'full_name', 'display_name'] as $attr) {
            $value = data_get($user, $attr);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $first = data_get($user, 'first_name');
        $last  = data_get($user, 'last_name');
        $combined = trim(((string) $first) . ' ' . ((string) $last));

        return $combined !== '' ? $combined : 'Anonymous Learner';
    }

    private function generateVerificationCode(): string
    {
        $bytes = (int) config('laravel-courses.certificates.verification_bytes', 8);
        if ($bytes < 1) {
            throw new RuntimeException('verification_bytes must be >= 1');
        }

        return strtoupper(bin2hex(random_bytes($bytes)));
    }

    private function generateCertificateNumber(): string
    {
        $prefix = (string) config('laravel-courses.certificates.number_prefix', 'CERT');
        $format = (string) config('laravel-courses.certificates.number_format', '{prefix}-{year}-{random}');
        $length = max(1, (int) config('laravel-courses.certificates.number_random_length', 6));

        $random = strtoupper(substr(base_convert(bin2hex(random_bytes(8)), 16, 36), 0, $length));
        $random = str_pad($random, $length, '0', STR_PAD_LEFT);

        return strtr($format, [
            '{prefix}' => $prefix,
            '{year}'   => date('Y'),
            '{random}' => $random,
        ]);
    }
}
