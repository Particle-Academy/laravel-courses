# Using particle-academy/laravel-courses — guide for agents

How an agent installs and uses this package in a consumer app. What the package
is, is in [README.md](./README.md). Notes for working ON this repo's code are in
[CONTRIBUTING.md](./CONTRIBUTING.md).

## Install

```bash
composer require particle-academy/laravel-courses
php artisan migrate
php artisan vendor:publish --tag=laravel-courses-config   # optional
```

- **Requires:** PHP `^8.4`, Laravel `^13.0`. Certificates are written by
  `particle-academy/fine-print` from `particle-academy/fancy-template`
  envelopes; both come in as dependencies.
- `CoursesServiceProvider` auto-discovers; migrations and routes load
  automatically. Routes mount under `api/courses` with `['api', 'auth:sanctum']`
  middleware by default (`laravel-courses.routes`).
- Set `laravel-courses.user_model` to the host's user model.
- Optional companions: `@particle-academy/classroom` (learner-facing React UI)
  and `particle-academy/teachers-aid` (authoring agent). Neither is required.

## Find it on the Fancy MCP server

The catalog server is `https://ui.particle.academy/mcp` (streamable HTTP, no API
key; MCP id `fancy-ui`). This package is not yet listed there; it appears once
it is in the registry. Server-side packages are found with
`search-backend-packages` (stack `php`).

## API essentials

- **Services own the behaviour**, and are what a host calls directly:
  `EnrollmentService`, `ProgressService`, `ScoringService`,
  `CertificateService`. Controllers are thin.
- **Enrollment is polymorphic:** a learner enrols in a `Curriculum` or a single
  `Course`, independently.
- **A test attaches at any level** (course, module or lesson), and all three
  count toward progress and completion.
- **`delivery_mode`** decides what progress is made of: `online` (lessons +
  tests), `in_person` (attendance hours only), `hybrid` (lessons + tests, plus
  attendance if `attendance_hours` is set).
- **Events** to listen for: `LearnerEnrolled`, `LessonCompleted`,
  `TestAttemptFinished`, `EnrollmentCompleted`, `CertificateIssued`,
  `CertificateRevoked` (namespace `ParticleAcademy\LaravelCourses\Events`).
- **Certificates** are PDFs from a fine-print JSON document, not HTML.
  `CertificateService::document()` returns the JSON before it is written;
  templates receive scalars only (`templateData()`).

## Traps

- **Authoring is denied by default.** Writes go through
  `AuthorizesCourseAdmin`, whose shipped binding refuses everyone. Bind your own
  rule in a service provider to switch authoring on. Reads and
  `GET verify/{code}` are public on purpose; mount the routes behind your own
  middleware if content must be private.
- **Self-enrolment is free-only by default** (`GatesSelfEnrollment` /
  `FreeEnrollmentOnly`). A host that sells courses enrols the buyer itself via
  `EnrollmentService` once payment confirms; bind your own gate to change the
  rule.
- **Learner identity comes from `LearnerResolver`** (`$request->user()` first).
  `allow_input_user_id` is off by default; turning it on trusts the caller's
  `user_id` / `X-Learner-Id` completely.
- **An `in_person` course's lessons do not count toward progress**, and
  attendance is admin-only, reads included.
- **Slugs are create-only.** They fill in from the title (with `-2` on
  collision) and are never rewritten when a title changes.
- **A certificate template is checked on save** (422 with paths), so fix the
  template rather than expecting a render-time error.
- The HTML certificate path (`renderHtml()`, `html` / `css` / `blade_view`,
  `default_view`, `template_attributes`, the `laravel-courses-views` tag) is
  deprecated and no longer affects the PDF.
