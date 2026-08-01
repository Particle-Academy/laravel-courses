# AGENTS.md — laravel-courses

Curriculums, courses, modules, lessons, tests, questions, enrollments, attempts
and certificates for Laravel. API-only: the host owns the user model and the UI.
`CLAUDE.md` symlinks here.

The learner-facing React surface is `@particle-academy/classroom`. The authoring
agent is `particle-academy/teachers-aid`. Neither is required.

## The shape

```
curriculum ──(curriculum_course)── course ── module ── lesson
                                     │         │        │
                                     └─────────┴────────┴── test ── question ── option
                                                              │
enrollment ── lesson_completion                          test_attempt ── attempt_answer
     └──────────────────────────────────────────────────────────────── certificate
```

- **Enrollment is polymorphic** — a learner enrols in a `Curriculum` *or* a bare
  `Course`, and the same learner can hold both independently.
- **A test attaches at any level.** `course_id`, `module_id` and `lesson_id` are
  each nullable, and all three count toward progress and completion.
- **Services own the behaviour**, not the models: `EnrollmentService`,
  `ProgressService`, `ScoringService`, `CertificateService`. Controllers are thin
  and the services are what a host should call directly.

## Rules

- **Deny by default.** Writes go through `AuthorizesCourseAdmin`, whose shipped
  binding refuses everyone. A host binds its own rule to switch authoring on.
  **Never widen this to "allow unless configured"** — it was exactly that once,
  and an anonymous request could mint a certificate for any user id. Reads and
  `GET verify/{code}` stay public on purpose.
- **`LearnerResolver` is the only source of learner identity.** Every ownership
  check compares against it, so a controller must never take a user id straight
  from the request. `allow_input_user_id` defaults to `false` and turning it on
  trusts the caller completely.
- **A certificate must not be issuable without completion.** `CertificateService::issue()`
  deliberately does NOT check progress — that is the admin short-circuit's job —
  so the *learner* path gates on `ProgressService::isFullyComplete()` in
  `CertificateController`. Keep that gate.
- **Slugs are NOT NULL and unique** on six tables. Bulk authoring must generate
  them; don't hand-roll `Str::slug()` per caller.
- **Never change grading silently.** Scoring semantics are covered by tests
  precisely because a change here retroactively alters who passed.

## `testIdsFor()` — read before touching it

`ProgressService::testIdsFor()` once queried `course_id` alone, so quizzes hung
off a module or a lesson were invisible to `summary()` and `isFullyComplete()` —
an enrollment reported complete, and could be certified, with them unpassed. It
now walks all three columns.

Three ways to break it again, each covered by a test:

- **Leaking scope.** The `orWhereIn`s must stay inside a closure, or the OR
  escapes its `where` and pulls in tests from courses the learner is not
  enrolled in.
- **Double counting.** A test naming `course_id`, `module_id` AND `lesson_id`
  is one test. Counting it per matching branch inflates the denominator and
  nobody can ever finish.
- **Curriculum fan-out.** A curriculum has to see module/lesson tests across
  *all* its courses, not just the first.

## Testing

```bash
composer install
vendor/bin/phpunit                                   # 58 tests
vendor/bin/phpunit --filter AuthorizationTest        # the security contract
```

`tests/TestCase.php` runs on `orchestra/testbench` with in-memory SQLite and
points `laravel-courses.user_model` at `Tests\Fixtures\TestUser` — that is what
keeps the host-model config an actual contract instead of a comment.

`tests/smoke-*.php` are NOT part of the suite. They run against a real host app
through `artisan tinker` and predate the tests; keep them for host-level checks,
but new coverage belongs in `tests/Feature`.

## Publishing

PHP package — auto-syncs to Packagist from git tags (no publish workflow). Ship =
bump → CHANGELOG in the same commit → tag `vX.Y.Z` → push tag. **First publish
requires a one-time Packagist submit + GitHub webhook.** Then advance the
envelope pin. See the envelope's `.ai/knowledge/publishing.md`.
