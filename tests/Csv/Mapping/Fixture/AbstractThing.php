<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

abstract class AbstractThing
{
    public function __construct(public string $value)
    {
    }
}
