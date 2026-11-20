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
  automatically. Routes mount under `api/courses` with
  `['api']` middleware by default (`laravel-courses.routes`); see "Route
  middleware" below before you ship.
- Set `laravel-courses.user_model` to the host's user model.
- Optional companions: `@particle-academy/classroom` (learner-facing React UI)
  and `particle-academy/teachers-aid` (authoring agent). Neither is required.

## Route middleware

The shipped default is `['api']`, and it stays that way on purpose: the
published catalogue (`GET courses`, `curriculums`, `tests`, ...) and
`GET verify/{code}` are public, and an authentication middleware on the whole
group would close them. `auth:sanctum` would also need Laravel Sanctum, which
this package does not require.

The learner routes (enrollments, lesson completion, attempts, a learner's
certificates) identify the learner with `$request->user()`, so the group needs
whatever makes that work in your app:

- **Session sign-in (Fortify, Breeze, a starter kit):** `['web']`. Learner
  routes then see the signed-in user; the catalogue stays public.
- **Token API where everything is private:** `['api', 'auth:sanctum']`.
  This closes the catalogue and certificate verification too.
- **Token API with a public catalogue:** `['api']`, with a token-reading guard
  as the app's default guard, because `$request->user()` asks the default
  guard. Check a learner route answers 200 with a token before you rely on it.

Without a signed-in user a learner route answers **401** and writes nothing.

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
  rule in a service provider to switch authoring on. Reads of PUBLISHED
  content and `GET verify/{code}` are public on purpose; mount the routes
  behind your own middleware if content must be private.
- **Drafts and the answer key belong to authors.** The same
  `AuthorizesCourseAdmin` rule decides who sees an unpublished course or
  curriculum (and its modules, lessons, tests and questions; everyone else
  gets a 404, and listings leave drafts out) and who receives `is_correct` on
  options and a question's `explanation`. Learners never receive the key, not
  when starting an attempt and not by passing `hide_answers=0`. An author can
  pass `hide_answers=1` to see the learner's view. Learners cannot self-enrol
  in a draft (422, as for a missing target).
- **Refusals are 4xx, never 500.** No signed-in learner is a 401
  (`Exceptions\LearnerNotAuthenticated`); a course rule refusing the request is
  `Exceptions\CourseRuleViolation`: 409 for a third attempt where two are
  allowed or a resubmitted attempt, 422 for a lesson outside the enrollment or
  impossible attendance hours. Both extend `RuntimeException`, so a host
  calling the services directly can keep catching that.
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
