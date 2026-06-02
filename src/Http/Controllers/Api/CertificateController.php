<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\CertificateResource;
use ParticleAcademy\LaravelCourses\Models\Certificate;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Support\LearnerResolver;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificates,
        private readonly ProgressService $progress,
        private readonly LearnerResolver $learner,
    ) {
    }

    public function issueForEnrollment(Request $request, Enrollment $enrollment): CertificateResource
    {
        $userId = $this->learner->resolve($request);
        abort_unless((string) $enrollment->user_id === (string) $userId, 403);

        abort_unless(
            $this->progress->isFullyComplete($enrollment->refresh()),
            409,
            'Enrollment is not fully complete.',
        );

        $certificate = $this->certificates->issue($enrollment);

        return new CertificateResource($certificate->load('template'));
    }

    public function show(Request $request, Certificate $certificate): CertificateResource
    {
        $this->assertOwnership($request, $certificate);

        return new CertificateResource($certificate->load(['template', 'enrollment']));
    }

    public function pdf(Request $request, Certificate $certificate): Response|StreamedResponse
    {
        $this->assertOwnership($request, $certificate);

        $pdf = $this->certificates->pdf($certificate);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf(
                'attachment; filename="certificate-%s.pdf"',
                $certificate->verification_code,
            ),
        ]);
    }

    public function verify(string $code): mixed
    {
        $certificate = $this->certificates->verify($code);

        if (! $certificate) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid'              => ! $certificate->isRevoked(),
            'verification_code'  => $certificate->verification_code,
            'certificate_number' => $certificate->certificate_number,
            'issued_at'          => $certificate->issued_at,
            'revoked_at'         => $certificate->revoked_at,
            'revocation_reason'  => $certificate->revocation_reason,
        ]);
    }

    public function revoke(Request $request, Certificate $certificate): CertificateResource
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $revoked = $this->certificates->revoke($certificate, $data['reason'] ?? null);

        return new CertificateResource($revoked->fresh());
    }

    private function assertOwnership(Request $request, Certificate $certificate): void
    {
        $userId = $this->learner->resolve($request);
        abort_unless((string) $certificate->enrollment->user_id === (string) $userId, 403);
    }
}
