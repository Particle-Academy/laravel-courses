# Changelog

Notable changes to `particle-academy/laravel-courses`.

**BREAKING** marks anything that can stop working on upgrade. This package is
pre-1.0, so breaking changes land in MINOR releases — read those entries before
upgrading.

---

## [Unreleased]

### Security

- **BREAKING — the authoring and admin routes are no longer open to everyone.**
  Every route was mounted with `middleware: ['api']` and nothing else, and no
  controller checked authorization. Against a default install, an unauthenticated
  request could create content (`201`), **delete** it (`204`, row gone), and
  **mint a real certificate for an arbitrary user id** (`201`, a valid
  `CERT-…` number issued). For a package whose entire value is that a
  certificate cannot be self-issued, that was the worst possible defect. This was
  reproduced against the suite, not inferred.

  Writes now sit behind a new `AuthorizesCourseAdmin` contract whose default
  binding, `DenyAllCourseAdmin`, **denies everyone** — the same deny-by-default
  shape `laravel-jobs` uses for employers. Reading the catalogue stays public,
  and so does certificate verification.

  **What you must DO:** bind your own rule, or authoring stays switched off.

  ```php
  $this->app->bind(AuthorizesCourseAdmin::class, fn () => new class implements AuthorizesCourseAdmin {
      public function allows(Request $request): bool
      {
          return $request->user()?->isInstructor() ?? false;
      }
  });
  ```

  Gated: all create/update/delete on curriculums, courses, modules, lessons,
  tests, questions, options and certificate templates, plus
  `POST admin/completions` and `POST certificates/{certificate}/revoke`.
  Ungated: every `index`/`show`, and `GET verify/{code}`.

- **BREAKING — `allow_input_user_id` now defaults to `false`.** It defaulted to
  `true` while not being declared in the config file at all, so hosts got the
  permissive behaviour without ever seeing the setting. It let any caller name
  the learner via a `user_id` input or `X-Learner-Id` header — which made every
  ownership check in the package decorative, since the controllers compare the
  enrollment's owner against exactly that claimed value.

  **What you must DO:** nothing, if your routes are behind auth middleware —
  an authenticated user has always taken precedence and still does. If you rely
  on a trusted server-to-server caller supplying `user_id`, set
  `LARAVEL_COURSES_ALLOW_INPUT_USER_ID=true` **and** ensure those routes are
  mounted behind middleware that authenticates the caller first.

### Added

- **A test suite.** The package shipped with no phpunit configuration and three
  `smoke-*.php` scripts that had to be run by hand through `artisan tinker`
  against a real host app. There are now **55 tests / 113 assertions** on
  `orchestra/testbench` covering enrollment, progress, scoring across all four
  question types, certificates, and the authorization contract above.
- **CI** — `.github/workflows/ci.yml`, matching the rest of the kit.
- `certificateNumber` is now a top-level certificate template variable. Only
  `verificationCode` was exposed, so a template could not print the number a
  holder actually quotes without reaching through the model.

### Known limitations

- **Module- and lesson-level tests do not count toward progress.** `tests`
  declares `course_id`, `module_id` and `lesson_id` as three independent
  nullable columns, but `ProgressService::testIdsFor()` only queries
  `course_id` — so a quiz attached to a module or a lesson is invisible, and an
  enrollment can read as fully complete with it unpassed. Covered by a test that
  documents the behaviour rather than asserting the ideal, because changing
  which tests count changes completion — and therefore certification — for
  existing enrollments. **Attach tests at course level until this is resolved.**
- An unknown placeholder in a certificate template renders empty rather than
  raising. Deliberate — a typo should not 500 someone's certificate download —
  but it does mean typos surface as blanks.
