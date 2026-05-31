<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\QuestionOption */
class QuestionOptionResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $hideAnswers = $request->boolean('hide_answers')
            || ($this->additional['hide_answers'] ?? false);

        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'is_correct' => $this->when(! $hideAnswers, $this->is_correct),
            'sort_order' => $this->sort_order,
        ];
    }
}
