<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Lesson */
class LessonResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'course_id'         => $this->course_id,
            'module_id'         => $this->module_id,
            'slug'              => $this->slug,
            'title'             => $this->title,
            'content_type'      => $this->content_type?->value,
            'content'           => $this->content,
            'video_url'         => $this->video_url,
            'sort_order'        => $this->sort_order,
            'estimated_minutes' => $this->estimated_minutes,
            'tests'             => TestResource::collection($this->whenLoaded('tests')),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
