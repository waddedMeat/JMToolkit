<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

final readonly class Address
{
    public function __construct(
        public string $street,
        public string $city,
        public ?string $zip = null,
    ) {
    }
}
