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
        // PDFs are written by particle-academy/fine-print from a
        // fancy-template document (JSON, not HTML). The template used when a
        // CertificateTemplate row has no `document`: null = the packaged
        // resources/certificates/default.fine-print.json, or a path to your
        // own envelope ({"fancyTemplate": 1, "writer": "fine-print", ...}).
        'default_template' => null,

        // Paper + orientation for the DEFAULT template only; a template's own
        // `document.page` decides for itself. fine-print draws letter, legal
        // and a4, in portrait or landscape.
        'paper'       => 'letter',
        'orientation' => 'landscape',

        // fine-print's `fonts` option: TrueType families to embed, e.g.
        // ['Times' => ['regular' => '/path/Serif.ttf', 'bold' => ...]].
        // null = fine-print's bundled Tinos (as Times) and Cousine (as
        // Courier), which print Latin, Greek and Cyrillic names exactly. The
        // unembedded standard fonts would print anything outside
        // Windows-1252 as "?".
        'fonts' => null,

        // Where issued certificate PDFs are persisted. Filesystem disk + path.
        'storage_disk' => env('LARAVEL_COURSES_CERT_DISK', 'local'),
        'storage_path' => 'certificates',

        // DEPRECATED, for renderHtml() only (removed in the next minor).
        // Blade view used when a CertificateTemplate row does not specify one.
        // It no longer affects the PDF.
        'default_view' => 'laravel-courses::certificates.default',

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

        // DEPRECATED with renderHtml(): the PDF's template data is scalars
        // only (CertificateService::templateData()) and never sees a model.
        // Attributes an INLINE template (`html` on a CertificateTemplate row)
        // may read through a dotted placeholder such as
        // `{{ recipient.email }}`. Inline templates are text stored through
        // the admin API, so they never see the live models: each one is
        // reduced to exactly these attributes before substitution. Anything
        // else — `recipient.password`, `recipient.remember_token`, a loaded
        // relation — renders empty, like any unknown placeholder.
        //
        // Widen per key if your template needs more, but every name you add
        // is printable into a PDF by anyone who can edit a template. Never
        // list a relation name, or the whole related model comes with it.
        'template_attributes' => [
            'certificate' => ['verification_code', 'certificate_number', 'issued_at'],
            'enrollment'  => ['status', 'started_at', 'completed_at'],
            'recipient'   => ['name', 'email'],
        ],
    ],
];
