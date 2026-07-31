<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ParticleAcademy\LaravelCourses\Concerns\GeneratesSlug;

class Test extends Model
{
    use GeneratesSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'passing_score'         => 'integer',
        'time_limit_seconds'    => 'integer',
        'max_attempts'          => 'integer',
        'is_final'              => 'boolean',
        'randomize_questions'   => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function effectivePassingScore(): int
    {
        return $this->passing_score ?? (int) config('laravel-courses.defaults.passing_score', 70);
    }

    public function effectiveMaxAttempts(): ?int
    {
        return $this->max_attempts ?? config('laravel-courses.defaults.max_attempts');
    }

    protected static function newFactory(): \ParticleAcademy\LaravelCourses\Database\Factories\TestFactory
    {
        return \ParticleAcademy\LaravelCourses\Database\Factories\TestFactory::new();
    }
}
