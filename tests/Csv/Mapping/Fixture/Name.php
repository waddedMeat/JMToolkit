<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

final readonly class Name
{
    public function __construct(
        public string $first,
        public string $last,
    ) {
    }
}
