<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
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
}
