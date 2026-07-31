<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ParticleAcademy\LaravelCourses\Concerns\GeneratesSlug;

class Module extends Model
{
    use GeneratesSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Slug is unique per course, not globally. */
    protected function slugScopeColumn(): ?string
    {
        return 'course_id';
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    protected static function newFactory(): \ParticleAcademy\LaravelCourses\Database\Factories\ModuleFactory
    {
        return \ParticleAcademy\LaravelCourses\Database\Factories\ModuleFactory::new();
    }
}
