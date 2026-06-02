<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\CurriculumResource;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

class CurriculumController extends Controller
{
    public function index(Request $request): mixed
    {
        $query = Curriculum::query()->orderBy('sort_order');

        if ($request->boolean('published_only')) {
            $query->where('is_published', true);
        }

        return CurriculumResource::collection($query->paginate((int) $request->query('per_page', 25)));
    }

    public function show(Curriculum $curriculum): CurriculumResource
    {
        $curriculum->load(['courses', 'certificateTemplate']);

        return new CurriculumResource($curriculum);
    }

    public function store(Request $request): CurriculumResource
    {
        $data = $request->validate([
            'slug'                    => 'required|string|max:255|unique:curriculums,slug',
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string',
            'sort_order'              => 'nullable|integer|min:0',
            'is_published'            => 'nullable|boolean',
            'price'                   => 'nullable|numeric|min:0',
            'currency'                => 'nullable|string|size:3',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'metadata'                => 'nullable|array',
        ]);

        return new CurriculumResource(Curriculum::create($data));
    }

    public function update(Request $request, Curriculum $curriculum): CurriculumResource
    {
        $data = $request->validate([
            'slug'                    => 'sometimes|string|max:255|unique:curriculums,slug,' . $curriculum->id,
            'title'                   => 'sometimes|string|max:255',
            'description'             => 'nullable|string',
            'sort_order'              => 'nullable|integer|min:0',
            'is_published'            => 'nullable|boolean',
            'price'                   => 'nullable|numeric|min:0',
            'currency'                => 'nullable|string|size:3',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'metadata'                => 'nullable|array',
        ]);

        $curriculum->update($data);

        return new CurriculumResource($curriculum->fresh(['courses', 'certificateTemplate']));
    }

    public function destroy(Curriculum $curriculum): JsonResponse
    {
        $curriculum->delete();

        return response()->json(null, 204);
    }

    public function attachCourse(Request $request, Curriculum $curriculum): CurriculumResource
    {
        $data = $request->validate([
            'course_id'   => 'required|integer|exists:courses,id',
            'sort_order'  => 'nullable|integer|min:0',
            'is_required' => 'nullable|boolean',
        ]);

        $curriculum->courses()->syncWithoutDetaching([
            $data['course_id'] => [
                'sort_order'  => $data['sort_order'] ?? 0,
                'is_required' => $data['is_required'] ?? true,
            ],
        ]);

        return new CurriculumResource($curriculum->fresh(['courses']));
    }

    public function detachCourse(Curriculum $curriculum, Course $course): JsonResponse
    {
        $curriculum->courses()->detach($course->id);

        return response()->json(null, 204);
    }
}
