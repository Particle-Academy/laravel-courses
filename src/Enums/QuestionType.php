<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case MultipleSelect = 'multiple_select';
    case TrueFalse      = 'true_false';
    case ShortAnswer    = 'short_answer';

    public function isAutoGradable(): bool
    {
        return $this !== self::ShortAnswer;
    }

    public function expectsMultipleAnswers(): bool
    {
        return $this === self::MultipleSelect;
    }
}
