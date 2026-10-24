<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A session the learner attended in person — the in-person counterpart of a
 * LessonCompletion.
 */
class Attendance extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attended_on' => 'date',
        'hours'       => 'decimal:2',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
