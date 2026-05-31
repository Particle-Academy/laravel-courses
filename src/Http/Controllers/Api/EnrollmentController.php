<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use ParticleAcademy\LaravelCourses\Http\Resources\EnrollmentResource;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Support\LearnerResolver;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ProgressService $progress,
        private readonly LearnerResolver $learner,
    ) {
    }

    public function index(Request $request): mixed
    {
        $userId = $this->learner->resolve($request);

        $enrollments = Enrollment::query()
            ->where('user_id', $userId)
            ->with(['enrollable', 'certificate'])
            ->orderByDesc('started_at')
            ->paginate((int) $request->query('per_page', 25));

        return EnrollmentResource::collection($enrollments);
    }

    public function show(Request $request, Enrollment $enrollment): EnrollmentResource
    {
        $this->assertOwnership($request, $enrollment);

        $enrollment->load([
            'enrollable',
            'lessonCompletions.lesson',
            'testAttempts.test',
            'certificate',
        ]);

        return EnrollmentResource::make($enrollment)->additional([
            'progress' => $this->progress->summary($enrollment),
        ]);
    }

    public function store(Request $request): EnrollmentResource
    {
        $data = $request->validate([
            'target_kind' => 'required|in:curriculum,course',
            'target_id'   => 'required_without:target_slug|integer',
            'target_slug' => 'required_without:target_id|string',
            'metadata'    => 'nullable|array',
        ]);

        $userId = $this->learner->resolve($request);

        $target = $this->resolveTarget($data);

        $enrollment = $this->enrollments->enroll($userId, $target, $data['metadata'] ?? []);

        return new EnrollmentResource($enrollment->fresh(['enrollable']));
    }

    public function destroy(Request $request, Enrollment $enrollment): JsonResponse
    {
        $this->assertOwnership($request, $enrollment);

        $this->enrollments->drop($enrollment);

        return response()->json(null, 204);
    }

    /**
     * @param  array{target_kind:string,target_id?:int|null,target_slug?:string|null}  $data
     */
    private function resolveTarget(array $data): Curriculum|Course
    {
        $modelClass = $data['target_kind'] === 'curriculum' ? Curriculum::class : Course::class;
        $query      = $modelClass::query();

        if (isset($data['target_id'])) {
            $target = $query->find($data['target_id']);
        } else {
            $target = $query->where('slug', $data['target_slug'])->first();
        }

        if (! $target) {
            throw ValidationException::withMessages([
                'target' => 'Target not found.',
            ]);
        }

        return $target;
    }

    private function assertOwnership(Request $request, Enrollment $enrollment): void
    {
        $userId = $this->learner->resolve($request);

        abort_unless((string) $enrollment->user_id === (string) $userId, 403);
    }
}
