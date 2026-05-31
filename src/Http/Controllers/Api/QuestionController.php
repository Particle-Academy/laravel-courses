<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelCourses\Http\Resources\QuestionResource;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test;

class QuestionController extends Controller
{
    public function index(Test $test): mixed
    {
        return QuestionResource::collection(
            $test->questions()->with('options')->get(),
        );
    }

    public function show(Test $test, Question $question): QuestionResource
    {
        abort_unless($question->test_id === $test->id, 404);

        $question->load('options');

        return new QuestionResource($question);
    }

    public function store(Request $request, Test $test): QuestionResource
    {
        $data = $request->validate([
            'prompt'      => 'required|string',
            'type'        => 'required|in:multiple_choice,multiple_select,true_false,short_answer',
            'points'      => 'nullable|numeric|min:0',
            'sort_order'  => 'nullable|integer|min:0',
            'explanation' => 'nullable|string',
            'options'                   => 'nullable|array',
            'options.*.label'           => 'required_with:options|string',
            'options.*.is_correct'      => 'nullable|boolean',
            'options.*.sort_order'      => 'nullable|integer|min:0',
        ]);

        $options = $data['options'] ?? [];
        unset($data['options']);

        return DB::transaction(function () use ($test, $data, $options): QuestionResource {
            $question = $test->questions()->create($data);

            foreach ($options as $i => $opt) {
                $question->options()->create([
                    'label'      => $opt['label'],
                    'is_correct' => (bool) ($opt['is_correct'] ?? false),
                    'sort_order' => $opt['sort_order'] ?? $i,
                ]);
            }

            return new QuestionResource($question->load('options'));
        });
    }

    public function update(Request $request, Test $test, Question $question): QuestionResource
    {
        abort_unless($question->test_id === $test->id, 404);

        $data = $request->validate([
            'prompt'      => 'sometimes|string',
            'type'        => 'sometimes|in:multiple_choice,multiple_select,true_false,short_answer',
            'points'      => 'nullable|numeric|min:0',
            'sort_order'  => 'nullable|integer|min:0',
            'explanation' => 'nullable|string',
        ]);

        $question->update($data);

        return new QuestionResource($question->fresh('options'));
    }

    public function destroy(Test $test, Question $question): JsonResponse
    {
        abort_unless($question->test_id === $test->id, 404);

        $question->delete();

        return response()->json(null, 204);
    }
}
