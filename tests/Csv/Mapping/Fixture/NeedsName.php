<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

final readonly class NeedsName
{
    public function __construct(public Name $name)
    {
    }
}
