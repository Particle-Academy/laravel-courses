<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestAttempt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'attempt_number' => 'integer',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
        'score'          => 'decimal:2',
        'points_awarded' => 'decimal:2',
        'max_score'      => 'decimal:2',
        'passed'         => 'boolean',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
