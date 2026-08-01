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
    | Trust a caller-supplied learner id
    |--------------------------------------------------------------------------
    | When true, a request with no authenticated user may identify the learner
    | with a `user_id` input or an `X-Learner-Id` header.
    |
    | This DEFEATS every ownership check in the package — the controllers do
    | verify that an enrollment belongs to the resolved learner, but a caller
    | who can name the learner passes that check trivially. Leave it off unless
    | the routes are mounted behind middleware that has already authenticated
    | the caller (a trusted server-to-server integration, say).
    */
    'allow_input_user_id' => env('LARAVEL_COURSES_ALLOW_INPUT_USER_ID', false),

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
        // verification_code is the URL-safe random token used in the public
        // verify endpoint. It is NOT the human-readable certificate number.
        'verification_bytes' => 8,

        // Human-readable certificate number format. Tokens replaced at issue:
        //   {prefix} → certificates.number_prefix
        //   {year}   → 4-digit issue year
        //   {random} → uppercased base36 of certificates.number_random_length
        'number_prefix'        => env('LARAVEL_COURSES_CERT_PREFIX', 'CERT'),
        'number_format'        => '{prefix}-{year}-{random}',
        'number_random_length' => 6,
    ],
];
