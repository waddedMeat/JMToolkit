<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

final readonly class UnionType
{
    public function __construct(public int|string $value)
    {
    }
}
