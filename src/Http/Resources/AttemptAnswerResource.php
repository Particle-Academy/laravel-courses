<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\AttemptAnswer */
class AttemptAnswerResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'test_attempt_id' => $this->test_attempt_id,
            'question_id'     => $this->question_id,
            'answer'          => $this->answer,
            'is_correct'      => $this->is_correct,
            'points_awarded'  => (float) $this->points_awarded,
            'question'        => new QuestionResource($this->whenLoaded('question')),
        ];
    }
}
