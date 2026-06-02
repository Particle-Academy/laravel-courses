<?php

declare(strict_types=1);

use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Services\CertificateService;
use ParticleAcademy\LaravelCourses\Services\EnrollmentService;

/** @var EnrollmentService $enrollments */
$enrollments = app(EnrollmentService::class);
/** @var CertificateService $certificates */
$certificates = app(CertificateService::class);

$course = Course::query()->first();
if (! $course) {
    echo "No course available — run the demo seeder first.\n";
    return;
}

$adminId = 777;
$learnerId = 1234;

$enrollment = $enrollments->enroll($learnerId, $course, ['issued_via' => 'admin']);
$completed = $enrollments->complete($enrollment);
$certificate = $certificates->issue(
    $completed->refresh(),
    issuedByUserId: $adminId,
    notes: 'Issued via admin smoke',
);

echo "Enrollment {$completed->id} status={$completed->status->value}\n";
echo "Certificate id={$certificate->id} number={$certificate->certificate_number} verification={$certificate->verification_code}\n";
echo "Issued by user id={$certificate->issued_by_user_id} notes='{$certificate->notes}'\n";
