<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
