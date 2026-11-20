<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ParticleAcademy\LaravelCourses\Support\ContentAccess;

/** @mixin \ParticleAcademy\LaravelCourses\Models\QuestionOption */
class QuestionOptionResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        // The answer key is for authors only; see ContentAccess::hidesAnswers().
        $hideAnswers = ContentAccess::hidesAnswers($request)
            || ($this->additional['hide_answers'] ?? false);

        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'is_correct' => $this->when(! $hideAnswers, $this->is_correct),
            'sort_order' => $this->sort_order,
        ];
    }
}
