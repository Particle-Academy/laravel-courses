<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use ParticleAcademy\LaravelCourses\Http\Resources\CertificateResource;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;

/**
 * Admin-side short-circuit: enrol a learner, mark their enrollment
 * completed, and issue a certificate in a single call. This is what an
 * admin uses for a manual / in-person completion that didn't flow
 * through the normal lessons + tests path.
 */
class AdminCompletionController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly CertificateService $certificates,
    ) {
    }

    public function store(Request $request): CertificateResource
    {
        $data = $request->validate([
            'user_id'           => 'required',
            'target_kind'       => 'required|in:curriculum,course',
            'target_id'         => 'required_without:target_slug|integer',
            'target_slug'       => 'required_without:target_id|string',
            'issued_by_user_id' => 'nullable',
            'notes'             => 'nullable|string|max:2000',
            'completed_at'      => 'nullable|date',
            'expires_at'        => 'nullable|date|after:now',
        ]);

        $target = $this->resolveTarget($data);

        $enrollment = $this->enrollments->enroll(
            $data['user_id'],
            $target,
            metadata: ['issued_via' => 'admin'],
            expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        if (isset($data['completed_at'])) {
            $enrollment->forceFill([
                'status'       => \ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus::Completed,
                'completed_at' => new \DateTimeImmutable($data['completed_at']),
            ])->save();
        } else {
            $enrollment = $this->enrollments->complete($enrollment);
        }

        $certificate = $this->certificates->issue(
            $enrollment->refresh(),
            issuedByUserId: $data['issued_by_user_id'] ?? null,
            notes: $data['notes'] ?? null,
        );

        return new CertificateResource($certificate->load(['template', 'enrollment']));
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function resolveTarget(array $data): Curriculum|Course
    {
        $modelClass = $data['target_kind'] === 'curriculum' ? Curriculum::class : Course::class;
        $query      = $modelClass::query();

        $target = isset($data['target_id'])
            ? $query->find($data['target_id'])
            : $query->where('slug', $data['target_slug'])->first();

        if (! $target) {
            throw ValidationException::withMessages(['target' => 'Target not found.']);
        }

        return $target;
    }
}
