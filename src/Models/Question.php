<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ParticleAcademy\LaravelCourses\Enums\QuestionType;

class Question extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'type'       => QuestionType::class,
        'points'     => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
