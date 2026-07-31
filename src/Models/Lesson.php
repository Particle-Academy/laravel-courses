<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ParticleAcademy\LaravelCourses\Concerns\GeneratesSlug;
use ParticleAcademy\LaravelCourses\Enums\LessonContentType;

class Lesson extends Model
{
    use GeneratesSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'content_type'      => LessonContentType::class,
        'sort_order'        => 'integer',
        'estimated_minutes' => 'integer',
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

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    protected static function newFactory(): \ParticleAcademy\LaravelCourses\Database\Factories\LessonFactory
    {
        return \ParticleAcademy\LaravelCourses\Database\Factories\LessonFactory::new();
    }
}
