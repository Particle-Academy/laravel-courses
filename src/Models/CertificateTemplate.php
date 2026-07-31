<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ParticleAcademy\LaravelCourses\Concerns\GeneratesSlug;

class CertificateTemplate extends Model
{
    use GeneratesSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_default'       => 'boolean',
        'variables_schema' => AsArrayObject::class,
    ];

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    protected static function newFactory(): \ParticleAcademy\LaravelCourses\Database\Factories\CertificateTemplateFactory
    {
        return \ParticleAcademy\LaravelCourses\Database\Factories\CertificateTemplateFactory::new();
    }
}
