<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

use JMToolkit\Csv\Mapping\ListOf;

final readonly class ListOfUnknown
{
    /** @param list<mixed> $value */
    public function __construct(
        #[ListOf('Nope\\Missing')]
        public array $value,
    ) {
    }
}
