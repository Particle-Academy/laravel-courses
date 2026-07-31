<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Fills in a URL slug when the caller does not supply one.
 *
 * Every slug column in this package is NOT NULL and unique, but nothing here
 * used to produce one — so the invariant lived only in the schema, and each
 * caller had to remember to hand-roll `Str::slug($title).'-'.Str::random(4)`.
 * Any caller that did not (an importer, a seeder, an authoring agent) got an
 * integrity-constraint violation instead of a course.
 *
 * The rule is create-only: an existing slug is never rewritten when a title
 * changes, because a published URL that silently moves is worse than an
 * out-of-date one. Pass a slug explicitly to override any of this.
 */
trait GeneratesSlug
{
    public static function bootGeneratesSlug(): void
    {
        static::creating(function (Model $model): void {
            if (! blank($model->getAttribute('slug'))) {
                return;
            }

            $model->setAttribute('slug', $model->generateSlug());
        });
    }

    /**
     * The text a slug is derived from. Override where the label is not `title`.
     */
    protected function slugSource(): string
    {
        return (string) ($this->getAttribute('title') ?? $this->getAttribute('name') ?? '');
    }

    /**
     * Column that scopes uniqueness, for the tables whose slug is unique per
     * parent rather than globally (`modules`, `lessons`). Null means global.
     */
    protected function slugScopeColumn(): ?string
    {
        return null;
    }

    protected function generateSlug(): string
    {
        $base = Str::slug($this->slugSource());

        if ($base === '') {
            // A title of "" or "第一课" slugs to nothing. Falling back to the
            // model name keeps the row creatable rather than failing on a
            // constraint the caller cannot see.
            $base = Str::slug(class_basename(static::class)) ?: 'item';
        }

        $base = Str::limit($base, 180, '');
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        if (($scope = $this->slugScopeColumn()) !== null) {
            $query->where($scope, $this->getAttribute($scope));
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        // The unique index is still the authority — this only keeps the common
        // case from needing a retry.
        return $query->exists();
    }
}
