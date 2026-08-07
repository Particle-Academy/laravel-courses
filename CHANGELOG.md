# Changelog

Notable changes to `particle-academy/laravel-courses`.

**BREAKING** marks anything that can stop working on upgrade. This package is
pre-1.0, so breaking changes land in MINOR releases — read those entries before
upgrading.

---

## [Unreleased]

## 0.2.0 — 2026-08-07

### Changed

- **BREAKING — PHP 8.3 is no longer supported.** `require.php` moves from `^8.3` to `^8.4`.

  **What you must do:** on PHP 8.4 or newer, nothing. On 8.3, either upgrade PHP first or stay on the previous release — it keeps working and is unaffected by this.

- CI now tests PHP 8.4 only, instead of a matrix spanning versions this package no longer claims to support. A matrix that tests what the manifest forbids is worse than none — it reports green for a combination nobody can install.

### Why

These are the kit 0.5 platform floors. The suite was split across PHP 8.2 and 8.3 with the framework spanning 11–13, so no package could rely on anything newer than its weakest sibling. Every PHP package in the kit takes the same floors at once, so a consumer never has to resolve a mix.

Pre-1.0, so this lands in a MINOR. **No API changed, nothing was removed, nothing was renamed** — only what the package requires.


## 0.1.0 — 2026-08-01

**First published release.** The package existed and was consumed from source
(GuardCard, via a path repo) long before it reached Packagist, so the BREAKING
notes below are aimed at that consumer — nothing can break for anyone
installing 0.1.0 fresh.

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
  against a real host app. There are now **58 tests / 116 assertions** on
  `orchestra/testbench` covering enrollment, progress, scoring across all four
  question types, certificates, and the authorization contract above.
- **CI** — `.github/workflows/ci.yml`, matching the rest of the kit.
- `certificateNumber` is now a top-level certificate template variable. Only
  `verificationCode` was exposed, so a template could not print the number a
  holder actually quotes without reaching through the model.

### Fixed

- **BREAKING (behaviour) — module- and lesson-level tests now count toward
  progress.** `tests` declares `course_id`, `module_id` and `lesson_id` as three
  independent nullable columns, but `ProgressService::testIdsFor()` queried
  `course_id` alone. A quiz attached to a module or a lesson was invisible to
  `summary()` and `isFullyComplete()`, so an enrollment reported **fully
  complete — and was therefore certifiable — with those quizzes unpassed.** A
  schema that permits an attachment the progress calculation cannot see has a
  hole in it; writing around it in every authoring tool would only spread the
  assumption.

  **What you must DO:** nothing, if every test you have sets `course_id` — the
  result is identical. If you have module- or lesson-level tests, enrollments
  that previously read complete may now read incomplete, because they are being
  measured against work that was always required and never counted. **Check
  before upgrading:**

  ```sql
  SELECT COUNT(*) FROM tests WHERE course_id IS NULL
    AND (module_id IS NOT NULL OR lesson_id IS NOT NULL);
  ```

  Already-issued certificates are unaffected — they are records, not
  recalculations.

### Known limitations

- An unknown placeholder in a certificate template renders empty rather than
  raising. Deliberate — a typo should not 500 someone's certificate download —
  but it does mean typos surface as blanks.
