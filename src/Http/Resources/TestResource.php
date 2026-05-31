<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Test */
class TestResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'title'               => $this->title,
            'description'         => $this->description,
            'course_id'           => $this->course_id,
            'module_id'           => $this->module_id,
            'lesson_id'           => $this->lesson_id,
            'passing_score'       => $this->effectivePassingScore(),
            'time_limit_seconds'  => $this->time_limit_seconds,
            'max_attempts'        => $this->effectiveMaxAttempts(),
            'is_final'            => $this->is_final,
            'randomize_questions' => $this->randomize_questions,
            'questions'           => QuestionResource::collection($this->whenLoaded('questions')),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
