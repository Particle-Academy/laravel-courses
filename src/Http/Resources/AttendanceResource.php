<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Attendance */
class AttendanceResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'enrollment_id'       => $this->enrollment_id,
            'attended_on'         => $this->attended_on?->toDateString(),
            'hours'               => (float) $this->hours,
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'notes'               => $this->notes,
            'created_at'          => $this->created_at,
        ];
    }
}
