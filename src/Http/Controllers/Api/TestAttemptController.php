<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\TestAttemptResource;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Test;
use ParticleAcademy\LaravelCourses\Models\TestAttempt;
use ParticleAcademy\LaravelCourses\Services\ScoringService;
use ParticleAcademy\LaravelCourses\Support\LearnerResolver;

class TestAttemptController extends Controller
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly LearnerResolver $learner,
    ) {
    }

    public function start(Request $request, Enrollment $enrollment, Test $test): TestAttemptResource
    {
        $this->assertOwnership($request, $enrollment);

        $attempt = $this->scoring->startAttempt($enrollment, $test);

        return new TestAttemptResource($attempt->load('test.questions.options'));
    }

    public function show(Request $request, TestAttempt $attempt): TestAttemptResource
    {
        $this->assertOwnership($request, $attempt->enrollment);

        $attempt->load(['test', 'answers.question.options']);

        return new TestAttemptResource($attempt);
    }

    public function submit(Request $request, TestAttempt $attempt): TestAttemptResource
    {
        $this->assertOwnership($request, $attempt->enrollment);

        $data = $request->validate([
            'answers'                 => 'required|array|min:1',
            'answers.*.question_id'   => 'required|integer|exists:questions,id',
            'answers.*.answer'        => 'required',
        ]);

        $graded = $this->scoring->submitAnswers($attempt, $data['answers']);

        return new TestAttemptResource($graded->load(['test', 'answers.question.options']));
    }

    private function assertOwnership(Request $request, Enrollment $enrollment): void
    {
        $userId = $this->learner->resolve($request);
        abort_unless((string) $enrollment->user_id === (string) $userId, 403);
    }
}
