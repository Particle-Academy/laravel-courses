<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\CourseResource;
use ParticleAcademy\LaravelCourses\Models\Course;

class CourseController extends Controller
{
    public function index(Request $request): mixed
    {
        $query = Course::query()->orderBy('sort_order');

        if ($request->boolean('published_only')) {
            $query->where('is_published', true);
        }

        return CourseResource::collection($query->paginate((int) $request->query('per_page', 25)));
    }

    public function show(Course $course): CourseResource
    {
        $course->load(['modules.lessons', 'lessons', 'tests', 'certificateTemplate']);

        return new CourseResource($course);
    }

    public function store(Request $request): CourseResource
    {
        $data = $request->validate([
            'slug'                    => 'required|string|max:255|unique:courses,slug',
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string',
            'sort_order'              => 'nullable|integer|min:0',
            'is_published'            => 'nullable|boolean',
            'estimated_minutes'       => 'nullable|integer|min:0',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'metadata'                => 'nullable|array',
        ]);

        return new CourseResource(Course::create($data));
    }

    public function update(Request $request, Course $course): CourseResource
    {
        $data = $request->validate([
            'slug'                    => 'sometimes|string|max:255|unique:courses,slug,' . $course->id,
            'title'                   => 'sometimes|string|max:255',
            'description'             => 'nullable|string',
            'sort_order'              => 'nullable|integer|min:0',
            'is_published'            => 'nullable|boolean',
            'estimated_minutes'       => 'nullable|integer|min:0',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'metadata'                => 'nullable|array',
        ]);

        $course->update($data);

        return new CourseResource($course->fresh());
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(null, 204);
    }
}
