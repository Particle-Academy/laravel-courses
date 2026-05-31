<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\CertificateController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\CertificateTemplateController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\CourseController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\CurriculumController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\EnrollmentController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\LessonCompletionController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\LessonController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\ModuleController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\QuestionController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\QuestionOptionController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\TestAttemptController;
use ParticleAcademy\LaravelCourses\Http\Controllers\Api\TestController;

/*
|--------------------------------------------------------------------------
| Content management (authoring)
|--------------------------------------------------------------------------
*/

Route::apiResource('curriculums', CurriculumController::class)->scoped(['curriculum' => 'slug']);
Route::post('curriculums/{curriculum:slug}/courses', [CurriculumController::class, 'attachCourse']);
Route::delete('curriculums/{curriculum:slug}/courses/{course:slug}', [CurriculumController::class, 'detachCourse']);

Route::apiResource('courses', CourseController::class)->scoped(['course' => 'slug']);

Route::apiResource('courses.modules', ModuleController::class)
    ->scoped(['course' => 'slug', 'module' => 'slug']);

Route::apiResource('courses.lessons', LessonController::class)
    ->scoped(['course' => 'slug', 'lesson' => 'slug']);

Route::apiResource('tests', TestController::class)->scoped(['test' => 'slug']);

Route::get('tests/{test:slug}/questions', [QuestionController::class, 'index']);
Route::post('tests/{test:slug}/questions', [QuestionController::class, 'store']);
Route::get('tests/{test:slug}/questions/{question}', [QuestionController::class, 'show']);
Route::match(['put', 'patch'], 'tests/{test:slug}/questions/{question}', [QuestionController::class, 'update']);
Route::delete('tests/{test:slug}/questions/{question}', [QuestionController::class, 'destroy']);

Route::get('questions/{question}/options', [QuestionOptionController::class, 'index']);
Route::post('questions/{question}/options', [QuestionOptionController::class, 'store']);
Route::match(['put', 'patch'], 'questions/{question}/options/{option}', [QuestionOptionController::class, 'update']);
Route::delete('questions/{question}/options/{option}', [QuestionOptionController::class, 'destroy']);

Route::apiResource('certificate-templates', CertificateTemplateController::class);

/*
|--------------------------------------------------------------------------
| Learner flow
|--------------------------------------------------------------------------
*/

Route::get('enrollments', [EnrollmentController::class, 'index']);
Route::post('enrollments', [EnrollmentController::class, 'store']);
Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show']);
Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy']);

Route::post(
    'enrollments/{enrollment}/lessons/{lesson}/complete',
    [LessonCompletionController::class, 'store'],
);

Route::post(
    'enrollments/{enrollment}/tests/{test}/attempts',
    [TestAttemptController::class, 'start'],
);
Route::get('attempts/{attempt}', [TestAttemptController::class, 'show']);
Route::post('attempts/{attempt}/submit', [TestAttemptController::class, 'submit']);

Route::post('enrollments/{enrollment}/certificate', [CertificateController::class, 'issueForEnrollment']);
Route::get('certificates/{certificate}', [CertificateController::class, 'show']);
Route::get('certificates/{certificate}/pdf', [CertificateController::class, 'pdf']);

/*
|--------------------------------------------------------------------------
| Public verification
|--------------------------------------------------------------------------
*/
Route::get('verify/{code}', [CertificateController::class, 'verify']);
