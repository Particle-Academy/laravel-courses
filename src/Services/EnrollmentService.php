<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelCourses\Enums\EnrollmentStatus;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;
use ParticleAcademy\LaravelCourses\Models\Enrollment;
use RuntimeException;

class EnrollmentService
{
    /**
     * Create (or return existing) enrollment of a user in a curriculum or course.
     *
     * @param  array<string,mixed>  $metadata
     */
    public function enroll(int|string $userId, Curriculum|Course $target, array $metadata = []): Enrollment
    {
        return Enrollment::firstOrCreate(
            [
                'user_id'         => $userId,
                'enrollable_type' => $target::class,
                'enrollable_id'   => $target->getKey(),
            ],
            [
                'status'     => EnrollmentStatus::Active,
                'started_at' => now(),
                'metadata'   => $metadata ?: null,
            ],
        );
    }

    public function complete(Enrollment $enrollment): Enrollment
    {
        if ($enrollment->isCompleted()) {
            return $enrollment;
        }

        $enrollment->forceFill([
            'status'       => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ])->save();

        return $enrollment->refresh();
    }

    public function drop(Enrollment $enrollment): Enrollment
    {
        $enrollment->forceFill(['status' => EnrollmentStatus::Dropped])->save();

        return $enrollment->refresh();
    }

    /**
     * Resolve the host user model row for an enrollment.
     */
    public function userFor(Enrollment $enrollment): ?Model
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('laravel-courses.user_model');

        if (! class_exists($userModel)) {
            throw new RuntimeException("Configured laravel-courses.user_model [{$userModel}] does not exist.");
        }

        return $userModel::query()->find($enrollment->user_id);
    }

    /**
     * Run a callback inside a transaction. Convenience for callers that
     * want to chain enrollment + completion + certificate atomically.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
