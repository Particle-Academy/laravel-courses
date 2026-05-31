<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Services;

use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelCourses\Enums\QuestionType;
use ParticleAcademy\LaravelCourses\Models\AttemptAnswer;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test;
use ParticleAcademy\LaravelCourses\Models\TestAttempt;
use RuntimeException;

class ScoringService
{
    public function __construct(
        private readonly EnrollmentService $enrollments,
        private readonly ProgressService $progress,
    ) {
    }

    public function startAttempt(Enrollment $enrollment, Test $test): TestAttempt
    {
        $max = $test->effectiveMaxAttempts();
        $existing = TestAttempt::query()
            ->where('enrollment_id', $enrollment->getKey())
            ->where('test_id', $test->getKey())
            ->count();

        if ($max !== null && $existing >= $max) {
            throw new RuntimeException("Maximum attempts ({$max}) reached for test #{$test->getKey()}.");
        }

        return TestAttempt::create([
            'enrollment_id'  => $enrollment->getKey(),
            'test_id'        => $test->getKey(),
            'attempt_number' => $existing + 1,
            'started_at'     => now(),
        ]);
    }

    /**
     * Submit and grade an attempt.
     *
     * @param  array<int,array<string,mixed>>  $answers  list of {question_id, answer}
     */
    public function submitAnswers(TestAttempt $attempt, array $answers): TestAttempt
    {
        if ($attempt->isFinished()) {
            throw new RuntimeException("Attempt #{$attempt->getKey()} is already finished.");
        }

        $test = $attempt->test()->with('questions.options')->first();
        if (! $test) {
            throw new RuntimeException("Attempt #{$attempt->getKey()} has no associated test.");
        }

        return DB::transaction(function () use ($attempt, $test, $answers): TestAttempt {
            $questions = $test->questions->keyBy('id');

            $pointsAwarded = 0.0;
            $maxScore      = 0.0;
            $hasManualGrade = false;

            $answersByQuestion = collect($answers)->keyBy('question_id');

            foreach ($questions as $question) {
                /** @var Question $question */
                $maxScore += (float) $question->points;

                $payload = $answersByQuestion->get($question->getKey());
                $answer  = $payload['answer'] ?? null;

                [$isCorrect, $points] = $this->gradeQuestion($question, $answer);
                $pointsAwarded += $points;
                $hasManualGrade = $hasManualGrade || ($isCorrect === null);

                AttemptAnswer::updateOrCreate(
                    [
                        'test_attempt_id' => $attempt->getKey(),
                        'question_id'     => $question->getKey(),
                    ],
                    [
                        'answer'         => $answer,
                        'is_correct'     => $isCorrect,
                        'points_awarded' => $points,
                    ],
                );
            }

            $scorePercent = $maxScore > 0
                ? round(($pointsAwarded / $maxScore) * 100, 2)
                : 0.0;

            $attempt->forceFill([
                'finished_at'    => now(),
                'points_awarded' => $pointsAwarded,
                'max_score'      => $maxScore,
                'score'          => $scorePercent,
                'passed'         => $hasManualGrade ? null : $scorePercent >= $test->effectivePassingScore(),
            ])->save();

            $enrollment = $attempt->enrollment()->first();
            if ($enrollment && $this->progress->isFullyComplete($enrollment->refresh())) {
                $this->enrollments->complete($enrollment);
            }

            return $attempt->refresh();
        });
    }

    /**
     * Manually grade a short-answer response.
     */
    public function gradeShortAnswer(AttemptAnswer $answer, bool $isCorrect, float $points): AttemptAnswer
    {
        $answer->forceFill([
            'is_correct'     => $isCorrect,
            'points_awarded' => $points,
        ])->save();

        $this->recalculateAttempt($answer->attempt);

        return $answer->refresh();
    }

    public function recalculateAttempt(TestAttempt $attempt): TestAttempt
    {
        $answers = $attempt->answers()->get();
        $pointsAwarded = $answers->sum(fn (AttemptAnswer $a) => (float) $a->points_awarded);
        $hasUngraded = $answers->contains(fn (AttemptAnswer $a) => $a->is_correct === null);

        $maxScore = (float) $attempt->max_score;
        $scorePercent = $maxScore > 0 ? round(($pointsAwarded / $maxScore) * 100, 2) : 0.0;

        $attempt->forceFill([
            'points_awarded' => $pointsAwarded,
            'score'          => $scorePercent,
            'passed'         => $hasUngraded ? null : $scorePercent >= $attempt->test->effectivePassingScore(),
        ])->save();

        return $attempt->refresh();
    }

    /**
     * @return array{0:bool|null,1:float}  [isCorrect, pointsAwarded]
     */
    private function gradeQuestion(Question $question, mixed $answer): array
    {
        $points = (float) $question->points;

        return match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse
                => $this->gradeSingleChoice($question, $answer, $points),
            QuestionType::MultipleSelect
                => $this->gradeMultiSelect($question, $answer, $points),
            QuestionType::ShortAnswer
                => [null, 0.0],
        };
    }

    /** @return array{0:bool,1:float} */
    private function gradeSingleChoice(Question $question, mixed $answer, float $points): array
    {
        $optionId = is_array($answer) ? ($answer['option_id'] ?? null) : $answer;
        if ($optionId === null) {
            return [false, 0.0];
        }

        $correct = $question->options->firstWhere('is_correct', true);
        $isCorrect = $correct && (int) $optionId === (int) $correct->getKey();

        return [$isCorrect, $isCorrect ? $points : 0.0];
    }

    /** @return array{0:bool,1:float} */
    private function gradeMultiSelect(Question $question, mixed $answer, float $points): array
    {
        $given = is_array($answer)
            ? array_map('intval', $answer['option_ids'] ?? $answer)
            : [];

        $correctIds = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($given);
        sort($correctIds);

        $isCorrect = $given === $correctIds && $correctIds !== [];

        return [$isCorrect, $isCorrect ? $points : 0.0];
    }
}
