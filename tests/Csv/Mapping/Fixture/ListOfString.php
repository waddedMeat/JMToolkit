<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

use JMToolkit\Csv\Mapping\ListOf;

final readonly class ListOfString
{
    public function __construct(
        #[ListOf('int')]
        public string $value,
    ) {
    }
}
