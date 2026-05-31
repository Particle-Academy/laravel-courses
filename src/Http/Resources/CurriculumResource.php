<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Curriculum */
class CurriculumResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'slug'         => $this->slug,
            'title'        => $this->title,
            'description'  => $this->description,
            'sort_order'   => $this->sort_order,
            'is_published' => $this->is_published,
            'metadata'     => $this->metadata,
            'courses'      => CourseResource::collection($this->whenLoaded('courses')),
            'certificate_template' => new CertificateTemplateResource($this->whenLoaded('certificateTemplate')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
