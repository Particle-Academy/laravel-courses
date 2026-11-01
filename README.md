# particle-academy/laravel-courses

[![Fancified](art/fancified.svg)](https://particle.academy)

Laravel package for selling and serving educational curriculums, courses, tests, and certificates. API-only — bring your own UI (or use the [`@particle-academy/classroom` React kit](#classroom-react-ux) built for it).

## Install

```sh
composer require particle-academy/laravel-courses
php artisan migrate
```

The package's `CoursesServiceProvider` auto-discovers; migrations and routes load automatically.

Publish what you want to override:

```sh
php artisan vendor:publish --tag=laravel-courses-config       # config/laravel-courses.php
php artisan vendor:publish --tag=laravel-courses-views        # deprecated: HTML views, no longer used by the PDF
php artisan vendor:publish --tag=laravel-courses-migrations   # if you need to customize the schema
```

## Configure

`config/laravel-courses.php`:

```php
'user_model'   => App\Models\User::class,           // host's user model
'routes' => [
    'enabled'    => true,
    'prefix'     => 'api/courses',
    'middleware' => ['api', 'auth:sanctum'],         // gate however you authenticate
],
'defaults' => [
    'passing_score' => 70,
    'max_attempts'  => null,
],
'certificates' => [
    'default_template' => null,                       // null = the packaged certificate
    'paper'          => 'letter',                     // letter | legal | a4 (default template only)
    'orientation'    => 'landscape',
    'fonts'          => null,                         // null = fine-print's bundled Tinos + Cousine
    'storage_disk'   => env('LARAVEL_COURSES_CERT_DISK', 'local'),
    'storage_path'   => 'certificates',
    'number_prefix'  => env('LARAVEL_COURSES_CERT_PREFIX', 'CERT'),
    'number_format'  => '{prefix}-{year}-{random}',
    'number_random_length' => 6,
],
```

## Domain

```
Curriculum ─< curriculum_course >─ Course ─< Module ─< Lesson
                                   Course ─< Test ─< Question ─< QuestionOption
                                   Module ─< Test
                                   Lesson ─< Test

Enrollment morphs to Curriculum OR Course
Enrollment ─< LessonCompletion
Enrollment ─< TestAttempt ─< AttemptAnswer
Enrollment ─── Certificate ─── CertificateTemplate
```

- **Tests can attach to a course, a module, or a single lesson.** Use whichever level fits the assessment.
- **Enrollments are polymorphic.** A learner can enroll in either a `Curriculum` (the whole program) or a single `Course`.
- **Certificates are signed by both a random `verification_code` (URL-safe) and a human-readable `certificate_number`** (`{prefix}-{year}-{random}` by default). Either resolves the public `verify` endpoint.

### Slugs

`Curriculum`, `Course`, `Test`, `CertificateTemplate`, `Module` and `Lesson` fill
in their own `slug` when you do not supply one — derived from the title, with a
`-2`, `-3` suffix on collision. `modules` and `lessons` are unique per course;
the rest are unique globally.

```php
Course::create(['title' => 'Patrol Basics']);   // slug: patrol-basics
Course::create(['title' => 'Patrol Basics']);   // slug: patrol-basics-2
```

Pass a `slug` explicitly to override. **Create-only** — an existing slug is never
rewritten when a title changes, because a published URL that silently moves is
worse than an out-of-date one.

## Endpoints

All routes mount under the configured prefix (default `/api/courses`).

### Authoring (CRUD)

| Method | Path | Notes |
|---|---|---|
| `GET / POST / GET-:slug / PATCH-:slug / DELETE-:slug` | `curriculums` | slug-bound |
| `POST` | `curriculums/{slug}/courses` | attach a course (sort_order, is_required) |
| `DELETE` | `curriculums/{slug}/courses/{slug}` | detach |
| `*` | `courses` | slug-bound; also accepts price, currency, highlights, is_required, hours |
| `*` | `courses/{slug}/modules`, `courses/{slug}/lessons` | nested |
| `*` | `tests`, `tests/{slug}/questions`, `questions/{id}/options` | nested |
| `*` | `certificate-templates` | one row should be `is_default: true` |

### Learner flow

| Method | Path | Notes |
|---|---|---|
| `GET` | `enrollments` | current learner's enrollments |
| `POST` | `enrollments` | enroll: `target_kind`, `target_slug`/`target_id`, optional `metadata` |
| `GET` | `enrollments/{id}` | includes progress summary |
| `DELETE` | `enrollments/{id}` | drops the enrollment |
| `POST` | `enrollments/{id}/lessons/{lesson}/complete` | marks lesson done |
| `POST` | `enrollments/{id}/tests/{test}/attempts` | starts an attempt |
| `GET` | `attempts/{id}` | full attempt with answers |
| `POST` | `attempts/{id}/submit` | grades and finalizes |
| `POST` | `enrollments/{id}/certificate` | issues once enrollment is fully complete |
| `GET` | `certificates/{id}` | learner's own certificate |
| `GET` | `certificates/{id}/pdf` | downloads the rendered PDF |

### Admin / public

| Method | Path | Notes |
|---|---|---|
| `POST` | `admin/completions` | short-circuit: enroll + complete + issue cert in one call (`user_id`, `target_kind`, `target_slug`/`target_id`, `issued_by_user_id`, `notes`, `completed_at`, `expires_at`) |
| `POST` | `certificates/{id}/revoke` | revoke with optional `reason` |
| `GET` | `verify/{code}` | public verify by `verification_code` OR `certificate_number`. Returns `valid: false` if revoked. |

## Learner resolution

`LearnerResolver` resolves the learner id for any request, in this order:

1. `$request->user()` (set by the host's auth middleware) — preferred.
2. `user_id` in the request body or `X-Learner-Id` header — useful for server-to-server callers or tests. Disable with `config('laravel-courses.allow_input_user_id', false)`.

## Events

Listen from your host app to wire audit logs, broadcasts, notifications:

```php
\ParticleAcademy\LaravelCourses\Events\LearnerEnrolled
\ParticleAcademy\LaravelCourses\Events\LessonCompleted
\ParticleAcademy\LaravelCourses\Events\TestAttemptFinished
\ParticleAcademy\LaravelCourses\Events\EnrollmentCompleted
\ParticleAcademy\LaravelCourses\Events\CertificateIssued
\ParticleAcademy\LaravelCourses\Events\CertificateRevoked
```

## Certificate templates

Certificate PDFs are written by [`particle-academy/fine-print`](https://github.com/Particle-Academy/fine-print)
(pure PHP, no active content) from a JSON document, not from HTML. A design is
a [`fancy-template`](https://github.com/Particle-Academy/fancy-template)
envelope: a fine-print document with `{{ tokens }}`, plus the variables it
declares.

```json
{ "fancyTemplate": 1, "writer": "fine-print",
  "variables": { "recipientName": { "type": "string", "required": true } },
  "document": { "page": { "size": "letter", "orientation": "landscape" },
                "blocks": [ { "type": "paragraph", "runs": [ { "text": "{{ recipientName }}" } ] } ] } }
```

- **Per template:** store the envelope in a `CertificateTemplate`'s `document`.
  The admin API checks it on save (`Template::inspect()`) and answers 422 with
  the paths of what is wrong, so a broken template never reaches a learner.
- **Default:** `resources/certificates/default.fine-print.json` (letter
  landscape, cream ground, double gold frame). Point
  `certificates.default_template` at your own file to replace it; `paper` and
  `orientation` apply to the default only.
- **Data** (`CertificateService::templateData()`), scalars only, never a model:

  ```
  title, recipientName, programName, programDetail, issuedAt (ISO date),
  issuer, verificationCode, certificateNumber
  ```

  A value that is absent is left out, so the template's declared `default`
  applies. Data a template requires and does not get is an error, not a blank.
- **Fonts:** by default the certificate embeds fine-print's bundled Tinos
  (registered as `Times`) and Cousine (`Courier`), so names in Polish, Greek or
  Cyrillic print exactly. Set `certificates.fonts` (fine-print's `fonts` option)
  to embed your own.

`CertificateService::document()` returns the fine-print JSON before it is
written, for previews or tests.

### Deprecated: HTML templates

`renderHtml()`, the `html` / `css` / `blade_view` columns, `default_view`,
`template_attributes` and the `laravel-courses-views` publish tag still work
for one minor, for HTML previews only. **They no longer affect the PDF**: a
template with no `document` gets the default certificate, and a warning is
logged. They are removed in the next minor.

## Classroom React UX

The companion React kit is [`@particle-academy/classroom`](https://github.com/Particle-Academy/classroom) on npm — built on `@particle-academy/react-fancy`, talks to this API.

Components:

- `CurriculumOverview` — list + enroll + progress per course
- `CoursePlayer` — lesson + test sidebar; main pane swaps modes
- `LessonView` — html / video / mixed lesson rendering
- `TestRunner` + `QuestionRenderer` — adapts to all four question types
- `CertificateView` — display + download

The `CoursesClient` (axios) covers the learner flow. Pass `learnerId` in its options to send `X-Learner-Id`.

## Tests

The suite runs standalone on Orchestra Testbench:

```sh
composer install
vendor/bin/phpunit
```

The shipped tests cover the full learner flow (enroll → complete lesson → pass test → issue certificate → public verify) and the revocation cycle. Factories for every model live under `database/factories/`.

For a quick interactive smoke, from a Laravel app that has the package installed:

```sh
php artisan tinker --execute="require '<absolute path>/tests/smoke-flow.php';"
```
