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
        $data = $request->validate($this->validationRules(null));

        return new CourseResource(Course::create($data));
    }

    public function update(Request $request, Course $course): CourseResource
    {
        $data = $request->validate($this->validationRules($course->id));

        $course->update($data);

        return new CourseResource($course->fresh());
    }

    /** @return array<string,mixed> */
    private function validationRules(?int $ignoreId): array
    {
        $unique = 'unique:courses,slug' . ($ignoreId ? ",{$ignoreId}" : '');
        $is = $ignoreId ? 'sometimes' : 'required';
        $isOpt = $ignoreId ? 'sometimes' : 'nullable';

        return [
            'slug'                    => "$is|string|max:255|$unique",
            'title'                   => "$is|string|max:255",
            'description'             => 'nullable|string',
            'sort_order'              => 'nullable|integer|min:0',
            'is_published'            => 'nullable|boolean',
            'is_required'             => 'nullable|boolean',
            'price'                   => 'nullable|numeric|min:0',
            'currency'                => 'nullable|string|size:3',
            'highlights'              => 'nullable|array',
            'highlights.*'            => 'string',
            'estimated_minutes'       => 'nullable|integer|min:0',
            'hours'                   => 'nullable|numeric|min:0',
            'certificate_template_id' => 'nullable|integer|exists:certificate_templates,id',
            'metadata'                => 'nullable|array',
        ];
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(null, 204);
    }
}
