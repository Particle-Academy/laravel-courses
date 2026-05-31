<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\LessonCompletion */
class LessonCompletionResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'lesson_id'     => $this->lesson_id,
            'completed_at'  => $this->completed_at,
            'lesson'        => new LessonResource($this->whenLoaded('lesson')),
        ];
    }
}
