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
            'id'                 => $this->id,
            'enrollment_id'      => $this->enrollment_id,
            'verification_code'  => $this->verification_code,
            'certificate_number' => $this->certificate_number,
            'issued_at'          => $this->issued_at,
            'issued_by_user_id'  => $this->issued_by_user_id,
            'revoked_at'         => $this->revoked_at,
            'revocation_reason'  => $this->revocation_reason,
            'is_revoked'         => $this->isRevoked(),
            'notes'              => $this->notes,
            'pdf_path'           => $this->pdf_path,
            'template'           => new CertificateTemplateResource($this->whenLoaded('template')),
            'metadata'           => $this->metadata,
        ];
    }
}
