<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use ParticleAcademy\LaravelCourses\Contracts\AuthorizesCourseAdmin;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Test;

/**
 * What a reader may see: drafts and the answer key belong to authors.
 *
 * "Author" is whatever the host's AuthorizesCourseAdmin rule allows, the same
 * rule that gates the write routes, so there is one definition of who may see
 * the inside of a course. The shipped binding allows nobody, so a default
 * install serves only published content and never the answer key.
 *
 * The rule is asked at most once per request: a listing renders a resource per
 * option, and a host rule may well touch the database.
 */
final class ContentAccess
{
    private const MEMO = 'laravel-courses.can_author';

    public static function canAuthor(Request $request): bool
    {
        if (! $request->attributes->has(self::MEMO)) {
            $request->attributes->set(self::MEMO, app(AuthorizesCourseAdmin::class)->allows($request));
        }

        return (bool) $request->attributes->get(self::MEMO);
    }

    /**
     * The answer key (`is_correct` on options, a question's `explanation`) is
     * shown to authors only. `hide_answers` lets an author ask for the
     * learner's view; nothing lets anyone else ask for the key.
     */
    public static function hidesAnswers(Request $request): bool
    {
        return $request->boolean('hide_answers') || ! self::canAuthor($request);
    }

    public static function courseIsVisible(Course $course, Request $request): bool
    {
        return (bool) $course->is_published || self::canAuthor($request);
    }

    public static function curriculumIsVisible(Curriculum $curriculum, Request $request): bool
    {
        return (bool) $curriculum->is_published || self::canAuthor($request);
    }

    public static function abortUnlessCourseVisible(Course $course, Request $request): void
    {
        abort_unless(self::courseIsVisible($course, $request), 404);
    }

    /**
     * A test is public when every course it hangs off (directly, through its
     * module, through its lesson) is published. A test attached to nothing has
     * no draft to belong to.
     */
    public static function testIsVisible(Test $test, Request $request): bool
    {
        if (self::canAuthor($request)) {
            return true;
        }

        return self::publicTests(Test::query())->whereKey($test->getKey())->exists();
    }

    public static function abortUnlessTestVisible(Test $test, Request $request): void
    {
        abort_unless(self::testIsVisible($test, $request), 404);
    }

    /**
     * Courses or curriculums: both carry `is_published`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function visibleCourses(Builder $query, Request $request): Builder
    {
        return self::canAuthor($request) ? $query : $query->where($query->qualifyColumn('is_published'), true);
    }

    /**
     * @param  Builder<Test>  $query
     * @return Builder<Test>
     */
    public static function visibleTests(Builder $query, Request $request): Builder
    {
        return self::canAuthor($request) ? $query : self::publicTests($query);
    }

    /**
     * @param  Builder<Test>  $query
     * @return Builder<Test>
     */
    private static function publicTests(Builder $query): Builder
    {
        $published = static fn (Builder $course): Builder => $course->where('is_published', true);

        return $query
            ->where(static fn (Builder $q) => $q->whereNull('course_id')->orWhereHas('course', $published))
            ->where(static fn (Builder $q) => $q->whereNull('module_id')->orWhereHas('module.course', $published))
            ->where(static fn (Builder $q) => $q->whereNull('lesson_id')->orWhereHas('lesson.course', $published));
    }
}
