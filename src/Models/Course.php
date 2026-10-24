<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use ParticleAcademy\LaravelCourses\Concerns\GeneratesSlug;
use ParticleAcademy\LaravelCourses\Enums\DeliveryMode;

class Course extends Model
{
    use GeneratesSlug;
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_published'       => 'boolean',
        'is_required'        => 'boolean',
        'delivery_mode'      => DeliveryMode::class,
        'sort_order'         => 'integer',
        'estimated_minutes'  => 'integer',
        'hours'              => 'decimal:2',
        'attendance_hours'   => 'decimal:2',
        'price'              => 'decimal:2',
        'highlights'         => AsArrayObject::class,
        'metadata'           => AsArrayObject::class,
    ];

    public function isOnline(): bool
    {
        return ($this->delivery_mode ?? DeliveryMode::Online)->isOnline();
    }

    public function isInPerson(): bool
    {
        return ($this->delivery_mode ?? DeliveryMode::Online)->isInPerson();
    }

    /**
     * In-person hours a learner must attend to complete this course.
     *
     * Only an in-person component is tracked: an online course needs none. On
     * an in_person course `hours` IS the seat time, so it is the fallback; on a
     * hybrid course `hours` is the whole course, online part included, so only
     * an explicit `attendance_hours` counts. Zero means attendance is not
     * tracked for this course.
     */
    public function requiredAttendanceHours(): float
    {
        if (! $this->isInPerson()) {
            return 0.0;
        }

        $hours = $this->attendance_hours
            ?? ($this->delivery_mode === DeliveryMode::InPerson ? $this->hours : null);

        return max(0.0, (float) $hours);
    }

    public function curriculums(): BelongsToMany
    {
        return $this->belongsToMany(Curriculum::class, 'curriculum_course')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function enrollments(): MorphMany
    {
        return $this->morphMany(Enrollment::class, 'enrollable');
    }

    protected static function newFactory(): \ParticleAcademy\LaravelCourses\Database\Factories\CourseFactory
    {
        return \ParticleAcademy\LaravelCourses\Database\Factories\CourseFactory::new();
    }
}
