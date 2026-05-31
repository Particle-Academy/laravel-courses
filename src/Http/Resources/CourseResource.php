<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Course */
class CourseResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'title'             => $this->title,
            'description'       => $this->description,
            'sort_order'        => $this->sort_order,
            'is_published'      => $this->is_published,
            'estimated_minutes' => $this->estimated_minutes,
            'metadata'          => $this->metadata,
            'modules'           => ModuleResource::collection($this->whenLoaded('modules')),
            'lessons'           => LessonResource::collection($this->whenLoaded('lessons')),
            'tests'             => TestResource::collection($this->whenLoaded('tests')),
            'certificate_template' => new CertificateTemplateResource($this->whenLoaded('certificateTemplate')),
            'pivot' => $this->whenPivotLoaded('curriculum_course', fn (): array => [
                'sort_order'  => $this->pivot->sort_order,
                'is_required' => (bool) $this->pivot->is_required,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
