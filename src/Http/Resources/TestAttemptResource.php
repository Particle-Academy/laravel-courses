<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\TestAttempt */
class TestAttemptResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'enrollment_id'  => $this->enrollment_id,
            'test_id'        => $this->test_id,
            'attempt_number' => $this->attempt_number,
            'started_at'     => $this->started_at,
            'finished_at'    => $this->finished_at,
            'score'          => $this->score !== null ? (float) $this->score : null,
            'points_awarded' => $this->points_awarded !== null ? (float) $this->points_awarded : null,
            'max_score'      => $this->max_score !== null ? (float) $this->max_score : null,
            'passed'         => $this->passed,
            'test'           => new TestResource($this->whenLoaded('test')),
            'answers'        => AttemptAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
