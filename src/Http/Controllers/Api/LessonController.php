<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\LessonResource;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Lesson;

class LessonController extends Controller
{
    public function index(Course $course): mixed
    {
        return LessonResource::collection(
            $course->lessons()->with('tests')->get(),
        );
    }

    public function show(Course $course, Lesson $lesson): LessonResource
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $lesson->load('tests');

        return new LessonResource($lesson);
    }

    public function store(Request $request, Course $course): LessonResource
    {
        $data = $request->validate([
            'slug'              => 'required|string|max:255',
            'title'             => 'required|string|max:255',
            'content_type'      => 'nullable|in:text,video,mixed',
            'content'           => 'nullable|string',
            'video_url'         => 'nullable|url|max:2048',
            'module_id'         => 'nullable|integer|exists:modules,id',
            'sort_order'        => 'nullable|integer|min:0',
            'estimated_minutes' => 'nullable|integer|min:0',
        ]);

        $lesson = $course->lessons()->create($data);

        return new LessonResource($lesson);
    }

    public function update(Request $request, Course $course, Lesson $lesson): LessonResource
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $data = $request->validate([
            'slug'              => 'sometimes|string|max:255',
            'title'             => 'sometimes|string|max:255',
            'content_type'      => 'nullable|in:text,video,mixed',
            'content'           => 'nullable|string',
            'video_url'         => 'nullable|url|max:2048',
            'module_id'         => 'nullable|integer|exists:modules,id',
            'sort_order'        => 'nullable|integer|min:0',
            'estimated_minutes' => 'nullable|integer|min:0',
        ]);

        $lesson->update($data);

        return new LessonResource($lesson->fresh());
    }

    public function destroy(Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->course_id === $course->id, 404);

        $lesson->delete();

        return response()->json(null, 204);
    }
}
