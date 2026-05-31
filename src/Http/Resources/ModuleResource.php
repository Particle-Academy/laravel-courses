<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Module */
class ModuleResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'course_id'   => $this->course_id,
            'slug'        => $this->slug,
            'title'       => $this->title,
            'description' => $this->description,
            'sort_order'  => $this->sort_order,
            'lessons'     => LessonResource::collection($this->whenLoaded('lessons')),
            'tests'       => TestResource::collection($this->whenLoaded('tests')),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
