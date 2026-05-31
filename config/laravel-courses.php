<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Host user model
    |--------------------------------------------------------------------------
    | The Eloquent model class used by the host application to represent
    | learners. Enrollments and certificates relate back to this model.
    */
    'user_model' => env('LARAVEL_COURSES_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Route mounting
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled'    => true,
        'prefix'     => 'api/courses',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        // Passing score (percentage 0-100) used when a Test has none defined.
        'passing_score' => 70,

        // Maximum attempts a learner can make on a single test. null = unlimited.
        'max_attempts' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate rendering
    |--------------------------------------------------------------------------
    */
    'certificates' => [
        // Blade view used when a CertificateTemplate row does not specify one.
        'default_view' => 'laravel-courses::certificates.default',

        // Where issued certificate PDFs are persisted. Filesystem disk + path.
        'storage_disk' => env('LARAVEL_COURSES_CERT_DISK', 'local'),
        'storage_path' => 'certificates',

        // dompdf paper + orientation.
        'paper'       => 'letter',
        'orientation' => 'landscape',

        // Verification code format: number of bytes (output is hex, so 2x chars).
        'verification_bytes' => 8,
    ],
];
