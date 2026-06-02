<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Curriculum extends Model
{
    use HasFactory;

    protected $table = 'curriculums';

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
        'price'        => 'decimal:2',
        'metadata'     => AsArrayObject::class,
    ];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'curriculum_course')
            ->withPivot(['sort_order', 'is_required'])
            ->withTimestamps()
            ->orderBy('curriculum_course.sort_order');
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function enrollments(): MorphMany
    {
        return $this->morphMany(Enrollment::class, 'enrollable');
    }

    protected static function newFactory(): \ParticleAcademy\LaravelCourses\Database\Factories\CurriculumFactory
    {
        return \ParticleAcademy\LaravelCourses\Database\Factories\CurriculumFactory::new();
    }
}
