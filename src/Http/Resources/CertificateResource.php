<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \ParticleAcademy\LaravelCourses\Models\Certificate */
class CertificateResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'enrollment_id'     => $this->enrollment_id,
            'verification_code' => $this->verification_code,
            'issued_at'         => $this->issued_at,
            'pdf_path'          => $this->pdf_path,
            'template'          => new CertificateTemplateResource($this->whenLoaded('template')),
            'metadata'          => $this->metadata,
        ];
    }
}
