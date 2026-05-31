<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Question */
class QuestionResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $hideAnswers = (bool) $request->boolean('hide_answers');

        return [
            'id'          => $this->id,
            'test_id'     => $this->test_id,
            'prompt'      => $this->prompt,
            'type'        => $this->type?->value,
            'points'      => (float) $this->points,
            'sort_order'  => $this->sort_order,
            'explanation' => $this->when(! $hideAnswers, $this->explanation),
            'options'     => QuestionOptionResource::collection(
                $this->whenLoaded('options')
            )->additional(['hide_answers' => $hideAnswers]),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
