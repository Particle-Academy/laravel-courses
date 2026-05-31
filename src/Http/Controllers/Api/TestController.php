<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\TestResource;
use ParticleAcademy\LaravelCourses\Models\Test;

class TestController extends Controller
{
    public function index(Request $request): mixed
    {
        $query = Test::query();

        if ($courseId = $request->integer('course_id')) {
            $query->where('course_id', $courseId);
        }
        if ($moduleId = $request->integer('module_id')) {
            $query->where('module_id', $moduleId);
        }
        if ($lessonId = $request->integer('lesson_id')) {
            $query->where('lesson_id', $lessonId);
        }

        return TestResource::collection(
            $query->with(['questions.options'])->paginate((int) $request->query('per_page', 25)),
        );
    }

    public function show(Request $request, Test $test): TestResource
    {
        $test->load(['questions.options']);

        return TestResource::make($test)->additional([
            'meta' => ['hide_answers' => $request->boolean('hide_answers')],
        ]);
    }

    public function store(Request $request): TestResource
    {
        $data = $request->validate([
            'slug'                => 'required|string|max:255|unique:tests,slug',
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'course_id'           => 'nullable|integer|exists:courses,id',
            'module_id'           => 'nullable|integer|exists:modules,id',
            'lesson_id'           => 'nullable|integer|exists:lessons,id',
            'passing_score'       => 'nullable|integer|min:0|max:100',
            'time_limit_seconds'  => 'nullable|integer|min:1',
            'max_attempts'        => 'nullable|integer|min:1',
            'is_final'            => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
        ]);

        return new TestResource(Test::create($data));
    }

    public function update(Request $request, Test $test): TestResource
    {
        $data = $request->validate([
            'slug'                => 'sometimes|string|max:255|unique:tests,slug,' . $test->id,
            'title'               => 'sometimes|string|max:255',
            'description'         => 'nullable|string',
            'course_id'           => 'nullable|integer|exists:courses,id',
            'module_id'           => 'nullable|integer|exists:modules,id',
            'lesson_id'           => 'nullable|integer|exists:lessons,id',
            'passing_score'       => 'nullable|integer|min:0|max:100',
            'time_limit_seconds'  => 'nullable|integer|min:1',
            'max_attempts'        => 'nullable|integer|min:1',
            'is_final'            => 'nullable|boolean',
            'randomize_questions' => 'nullable|boolean',
        ]);

        $test->update($data);

        return new TestResource($test->fresh());
    }

    public function destroy(Test $test): JsonResponse
    {
        $test->delete();

        return response()->json(null, 204);
    }
}
