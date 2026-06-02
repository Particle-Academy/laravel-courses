<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Enrollment */
class EnrollmentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $target = $this->whenLoaded('enrollable');

        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'status'          => $this->status?->value,
            'started_at'      => $this->started_at,
            'completed_at'    => $this->completed_at,
            'expires_at'      => $this->expires_at,
            'is_expired'      => $this->isExpired(),
            'enrollable_type' => $this->enrollable_type,
            'enrollable_id'   => $this->enrollable_id,
            'target_kind'     => match (true) {
                $target instanceof Curriculum => 'curriculum',
                $target instanceof Course     => 'course',
                default                       => null,
            },
            'target' => match (true) {
                $target instanceof Curriculum => new CurriculumResource($target),
                $target instanceof Course     => new CourseResource($target),
                default                       => null,
            },
            'progress'        => $this->additional['progress'] ?? null,
            'lesson_completions' => LessonCompletionResource::collection(
                $this->whenLoaded('lessonCompletions')
            ),
            'test_attempts'   => TestAttemptResource::collection($this->whenLoaded('testAttempts')),
            'certificate'     => new CertificateResource($this->whenLoaded('certificate')),
            'metadata'        => $this->metadata,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }
}
