<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\ModuleResource;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Module;

class ModuleController extends Controller
{
    public function index(Course $course): mixed
    {
        return ModuleResource::collection(
            $course->modules()->with('lessons')->get(),
        );
    }

    public function show(Course $course, Module $module): ModuleResource
    {
        abort_unless($module->course_id === $course->id, 404);

        $module->load(['lessons', 'tests']);

        return new ModuleResource($module);
    }

    public function store(Request $request, Course $course): ModuleResource
    {
        $data = $request->validate([
            'slug'        => 'required|string|max:255',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $module = $course->modules()->create($data);

        return new ModuleResource($module);
    }

    public function update(Request $request, Course $course, Module $module): ModuleResource
    {
        abort_unless($module->course_id === $course->id, 404);

        $data = $request->validate([
            'slug'        => 'sometimes|string|max:255',
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        $module->update($data);

        return new ModuleResource($module->fresh());
    }

    public function destroy(Course $course, Module $module): JsonResponse
    {
        abort_unless($module->course_id === $course->id, 404);

        $module->delete();

        return response()->json(null, 204);
    }
}
