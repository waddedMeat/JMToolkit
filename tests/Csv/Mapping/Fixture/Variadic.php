<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

final readonly class Variadic
{
    /** @var list<string> */
    public array $values;

    public function __construct(string ...$values)
    {
        $this->values = array_values($values);
    }
}
