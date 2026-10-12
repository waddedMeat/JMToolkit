<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

use JMToolkit\Csv\Mapping\ListOf;

/** Not readonly: readonly properties cannot be untyped, and $untyped must be. */
final class Scalars
{
    /**
     * @param mixed $untyped
     * @param list<int> $scores
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public int $int,
        public float $float,
        public bool $bool,
        public string $string,
        public ?int $nullableInt,
        public ?string $nullableString,
        public mixed $mixed,
        public $untyped,
        public Priority $priority,
        public Color $color,
        public \DateTimeInterface $date,
        public \DateTime $mutableDate,
        #[ListOf('int')]
        public array $scores,
        public array $raw,
    ) {
    }
}
