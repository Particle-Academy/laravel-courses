<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus;

class Enrollment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status'       => EnrollmentStatus::class,
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'metadata'     => AsArrayObject::class,
    ];

    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('laravel-courses.user_model');

        return $this->belongsTo($model);
    }

    public function enrollable(): MorphTo
    {
        return $this->morphTo();
    }

    public function lessonCompletions(): HasMany
    {
        return $this->hasMany(LessonCompletion::class);
    }

    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === EnrollmentStatus::Completed;
    }
}
