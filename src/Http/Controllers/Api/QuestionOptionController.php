<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelCourses\Http\Resources\QuestionOptionResource;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\QuestionOption;

class QuestionOptionController extends Controller
{
    public function index(Question $question): mixed
    {
        return QuestionOptionResource::collection($question->options);
    }

    public function store(Request $request, Question $question): QuestionOptionResource
    {
        $data = $request->validate([
            'label'      => 'required|string',
            'is_correct' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $option = $question->options()->create($data);

        return new QuestionOptionResource($option);
    }

    public function update(Request $request, Question $question, QuestionOption $option): QuestionOptionResource
    {
        abort_unless($option->question_id === $question->id, 404);

        $data = $request->validate([
            'label'      => 'sometimes|string',
            'is_correct' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $option->update($data);

        return new QuestionOptionResource($option->fresh());
    }

    public function destroy(Question $question, QuestionOption $option): JsonResponse
    {
        abort_unless($option->question_id === $question->id, 404);

        $option->delete();

        return response()->json(null, 204);
    }
}
