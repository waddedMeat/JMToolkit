<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

final readonly class Defaults
{
    public function __construct(
        public string $first,
        public int $middle = 5,
        public string $last = 'default',
    ) {
    }
}
