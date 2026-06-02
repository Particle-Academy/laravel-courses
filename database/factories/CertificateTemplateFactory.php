<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelCourses\Models\CertificateTemplate;

/** @extends Factory<CertificateTemplate> */
class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug'       => \Illuminate\Support\Str::slug($name) . '-' . $this->faker->unique()->randomNumber(5),
            'name'       => $name,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
