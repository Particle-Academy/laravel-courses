<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Database\Factories;

use OverflowException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A first-party, deterministic stand-in for the handful of Faker methods this
 * package's factories call. It exists so the package needs no third-party fake
 * data library: fakerphp/faker fails the suite's freshness bar.
 *
 * Factories use the host's Faker when the host has one (see UsesFakeData), so a
 * consumer's seeding and locale still apply. This class is the fallback, and the
 * only source this package's own suite uses.
 *
 * Seeded: the same seed yields the same sequence. Call reset() to restart it.
 * Only the methods the factories actually call are provided.
 */
final class FakeData
{
    public const DEFAULT_SEED = 20261118;

    private const WORDS = [
        'alpha', 'amber', 'anchor', 'arc', 'atlas', 'beacon', 'birch', 'bloom',
        'bridge', 'cedar', 'circuit', 'cobalt', 'comet', 'coral', 'delta', 'drift',
        'ember', 'falcon', 'fern', 'field', 'flint', 'forge', 'garnet', 'glade',
        'harbor', 'helix', 'iris', 'jade', 'kernel', 'lantern', 'lumen', 'maple',
        'meadow', 'nova', 'oak', 'orbit', 'pebble', 'prism', 'quartz', 'ridge',
        'river', 'sage', 'signal', 'summit', 'tidal', 'umber', 'vector', 'willow',
    ];

    private static ?self $instance = null;

    private Randomizer $random;

    /** @var array<string, array<string, true>> values already returned through unique(), per method */
    private array $seen = [];

    private function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(self::DEFAULT_SEED);
    }

    /** Restart the sequence (and forget unique() history) from the given seed. */
    public static function reset(int $seed = self::DEFAULT_SEED): void
    {
        self::$instance = new self($seed);
    }

    /**
     * A proxy whose calls never repeat a value for the same method, like Faker's
     * unique(). Gives up after 10,000 attempts, as Faker does.
     */
    public function unique(): object
    {
        $data = $this;

        return new class ($data) {
            public function __construct(private FakeData $data) {}

            /** @param array<int, mixed> $arguments */
            public function __call(string $method, array $arguments): mixed
            {
                return $this->data->uniqueCall($method, $arguments);
            }
        };
    }

    /** @param array<int, mixed> $arguments */
    public function uniqueCall(string $method, array $arguments): mixed
    {
        for ($attempt = 0; $attempt < 10000; $attempt++) {
            $value = $this->{$method}(...$arguments);
            $key = serialize($value);
            if (! isset($this->seen[$method][$key])) {
                $this->seen[$method][$key] = true;

                return $value;
            }
        }

        throw new OverflowException("FakeData: no unique value for {$method}() after 10000 attempts.");
    }

    public function word(): string
    {
        return self::WORDS[$this->random->getInt(0, count(self::WORDS) - 1)];
    }

    /** @return ($asText is true ? string : list<string>) */
    public function words(int $count = 3, bool $asText = false): array|string
    {
        $words = [];
        for ($i = 0; $i < $count; $i++) {
            $words[] = $this->word();
        }

        return $asText ? implode(' ', $words) : $words;
    }

    public function sentence(int $words = 6): string
    {
        return ucfirst($this->words(max(1, $words), true)) . '.';
    }

    public function paragraph(int $sentences = 3): string
    {
        $out = [];
        for ($i = 0; $i < max(1, $sentences); $i++) {
            $out[] = $this->sentence($this->random->getInt(4, 8));
        }

        return implode(' ', $out);
    }

    public function numberBetween(int $min = 0, int $max = 2147483647): int
    {
        return $this->random->getInt(min($min, $max), max($min, $max));
    }

    /** A number of up to $digits digits (0 .. 10^digits - 1). */
    public function randomNumber(int $digits = 9): int
    {
        return $this->random->getInt(0, (10 ** max(1, $digits)) - 1);
    }
}
