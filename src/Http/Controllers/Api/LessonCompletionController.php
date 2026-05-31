<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\LessonCompletionResource;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Support\LearnerResolver;

class LessonCompletionController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly LearnerResolver $learner,
    ) {
    }

    public function store(Request $request, Enrollment $enrollment, Lesson $lesson): LessonCompletionResource
    {
        $userId = $this->learner->resolve($request);
        abort_unless((string) $enrollment->user_id === (string) $userId, 403);

        $completion = $this->progress->markLessonComplete($enrollment, $lesson);

        return new LessonCompletionResource($completion);
    }
}
