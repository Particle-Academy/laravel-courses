<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Tests\Feature\FactoryDataTest;

use ParticleAcademy\LaravelCourses\Database\Factories\CourseFactory;
use ParticleAcademy\LaravelCourses\Database\Factories\FakeData;
use ParticleAcademy\LaravelCourses\Models\Course;
use ParticleAcademy\LaravelCourses\Models\Curriculum;

/*
| The shipped factories carry no third-party fake-data dependency. They use the
| host's Faker when it has one and FakeData otherwise.
*/

test('the suite runs without a fake data library installed', function () {
    // The point of FakeData: if Faker crept back in transitively, the fallback
    // path below would stop being the one under test.
    expect(class_exists(\Faker\Generator::class))->toBeFalse();
});

test('the shipped factories create rows with no Faker present', function () {
    $course = Course::factory()->create();
    $curriculum = Curriculum::factory()->create();

    expect($course->title)->toBeString()->not->toBe('')
        ->and($course->slug)->toMatch('/^[a-z0-9-]+-\d+$/')
        ->and($course->estimated_minutes)->toBeGreaterThanOrEqual(15)->toBeLessThanOrEqual(240)
        ->and($curriculum->description)->toBeString()->not->toBe('');
});

test('the same seed gives the same data, so fixtures are deterministic', function () {
    FakeData::reset(42);
    $first = [FakeData::instance()->sentence(3), FakeData::instance()->paragraph(), FakeData::instance()->randomNumber(5)];

    FakeData::reset(42);
    $second = [FakeData::instance()->sentence(3), FakeData::instance()->paragraph(), FakeData::instance()->randomNumber(5)];

    FakeData::reset(43);
    $other = [FakeData::instance()->sentence(3), FakeData::instance()->paragraph(), FakeData::instance()->randomNumber(5)];

    expect($second)->toBe($first)->and($other)->not->toBe($first);
});

test('unique() never repeats a value for the same method', function () {
    $data = FakeData::instance();
    $values = [];
    for ($i = 0; $i < 500; $i++) {
        $values[] = $data->unique()->randomNumber(3);
    }

    expect(array_unique($values))->toHaveCount(500);
});

test('unique() gives up, as Faker does, when the space is exhausted', function () {
    $data = FakeData::instance();
    for ($i = 0; $i < 10; $i++) {
        $data->unique()->randomNumber(1);
    }

    $data->unique()->randomNumber(1);
})->throws(\OverflowException::class);

test('a host Faker is used in preference to FakeData', function () {
    // A host with Faker installed: Laravel's Factory resolves it into $this->faker.
    $hostFaker = new class {
        public function unique(): static { return $this; }
        public function sentence(int $n = 6): string { return 'Host Faker Title'; }
        public function randomNumber(int $d = 9): int { return 77; }
        public function paragraph(int $n = 3): string { return 'host paragraph'; }
        public function numberBetween(int $a = 0, int $b = 0): int { return 99; }
    };

    $factory = new class ($hostFaker) extends CourseFactory {
        public function __construct(private object $host) { parent::__construct(); }
        protected function withFaker() { return $this->host; }
    };

    $row = $factory->definition();

    expect($row['title'])->toBe('Host Faker Title')
        ->and($row['slug'])->toBe('host-faker-title-77')
        ->and($row['description'])->toBe('host paragraph')
        ->and($row['estimated_minutes'])->toBe(99);
});
