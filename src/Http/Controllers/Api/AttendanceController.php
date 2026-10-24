<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use ParticleAcademy\LaravelCourses\Http\Resources\AttendanceResource;
use ParticleAcademy\LaravelCourses\Models\Attendance;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use RuntimeException;

/**
 * In-person attendance. Admin-gated: attending is something an instructor
 * vouches for, so a learner can never record their own hours.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function index(Enrollment $enrollment): AnonymousResourceCollection
    {
        return AttendanceResource::collection($enrollment->attendances()->get());
    }

    public function store(Request $request, Enrollment $enrollment): AttendanceResource
    {
        $data = $request->validate([
            'hours'               => 'required|numeric|gt:0|max:24',
            'attended_on'         => 'required|date|before_or_equal:today',
            'recorded_by_user_id' => 'nullable',
            'notes'               => 'nullable|string|max:2000',
        ]);

        try {
            $attendance = $this->progress->recordAttendance(
                $enrollment,
                (float) $data['hours'],
                new \DateTimeImmutable($data['attended_on']),
                $data['recorded_by_user_id'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['enrollment' => $e->getMessage()]);
        }

        return new AttendanceResource($attendance);
    }

    /**
     * Removing a mistaken entry does NOT reopen a completed enrollment. A
     * certificate may already be out; withdrawing it is a revocation, which
     * is its own deliberate act.
     */
    public function destroy(Enrollment $enrollment, Attendance $attendance): JsonResponse
    {
        abort_unless((int) $attendance->enrollment_id === (int) $enrollment->getKey(), 404);

        $attendance->delete();

        return response()->json(null, 204);
    }
}
