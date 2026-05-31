<?php

declare(strict_types=1);

/*
 * Quick end-to-end smoke. Run from the sandbox app:
 *   php artisan tinker --execute="require '<absolute path>/smoke-flow.php';"
 *
 * Creates a tiny curriculum + course + lesson + test, enrolls a learner,
 * completes everything, issues a certificate, and prints the verification
 * code so you can curl the public verify endpoint.
 */

use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelCourses\Models\AttemptAnswer;
use ParticleAcademy\LaravelCourses\Models\CertificateTemplate;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Lesson;
use ParticleAcademy\LaravelCourses\Models\Question;
use ParticleAcademy\LaravelCourses\Models\Test;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;
use ParticleAcademy\LaravelCourses\Services\ProgressService;
use ParticleAcademy\LaravelCourses\Services\ScoringService;

DB::transaction(function (): void {
    $template = CertificateTemplate::firstOrCreate(
        ['slug' => 'default-smoke'],
        ['name' => 'Smoke default', 'is_default' => true],
    );

    $curriculum = Curriculum::firstOrCreate(['slug' => 'smoke-curriculum'], [
        'title'        => 'Smoke Curriculum',
        'description'  => 'Smoke-test curriculum.',
        'is_published' => true,
        'certificate_template_id' => $template->id,
    ]);

    $course = Course::firstOrCreate(['slug' => 'smoke-course'], [
        'title'        => 'Smoke Course',
        'description'  => 'Smoke-test course.',
        'is_published' => true,
    ]);

    $curriculum->courses()->syncWithoutDetaching([
        $course->id => ['sort_order' => 0, 'is_required' => true],
    ]);

    $lesson = Lesson::firstOrCreate(
        ['course_id' => $course->id, 'slug' => 'intro'],
        ['title' => 'Intro', 'content' => 'Hello.', 'content_type' => 'text', 'sort_order' => 0],
    );

    $test = Test::firstOrCreate(['slug' => 'smoke-final'], [
        'title'         => 'Smoke Final',
        'course_id'     => $course->id,
        'passing_score' => 50,
        'is_final'      => true,
    ]);

    $question = Question::firstOrCreate(
        ['test_id' => $test->id, 'prompt' => 'Is the sky blue?'],
        ['type' => 'true_false', 'points' => 1, 'sort_order' => 0],
    );
    if ($question->options()->count() === 0) {
        $question->options()->createMany([
            ['label' => 'True',  'is_correct' => true,  'sort_order' => 0],
            ['label' => 'False', 'is_correct' => false, 'sort_order' => 1],
        ]);
    }

    /** @var EnrollmentService $enrollments */
    $enrollments = app(EnrollmentService::class);
    /** @var ProgressService $progress */
    $progress = app(ProgressService::class);
    /** @var ScoringService $scoring */
    $scoring = app(ScoringService::class);
    /** @var CertificateService $certificates */
    $certificates = app(CertificateService::class);

    $userId = 999;
    $enrollment = $enrollments->enroll($userId, $curriculum);
    $progress->markLessonComplete($enrollment, $lesson);

    $attempt = $scoring->startAttempt($enrollment, $test);
    $correctOptionId = $question->options()->where('is_correct', true)->value('id');
    $scoring->submitAnswers($attempt, [
        ['question_id' => $question->id, 'answer' => ['option_id' => $correctOptionId]],
    ]);

    $enrollment->refresh();
    $summary = $progress->summary($enrollment);

    $certificate = $certificates->issue($enrollment);

    echo "Enrollment: {$enrollment->id} status={$enrollment->status->value}\n";
    echo "Summary: " . json_encode($summary) . "\n";
    echo "Certificate: id={$certificate->id} code={$certificate->verification_code}\n";

    AttemptAnswer::query()->where('test_attempt_id', $attempt->id)->each(function (AttemptAnswer $a): void {
        echo "  Answer Q{$a->question_id} correct=" . var_export($a->is_correct, true) . " pts={$a->points_awarded}\n";
    });

    // Render the certificate HTML to make sure the template path works.
    $html = $certificates->renderHtml($certificate);
    echo "Cert HTML length: " . strlen($html) . "\n";
});

echo "Smoke OK\n";
