<?php

declare(strict_types=1);

namespace JMToolkit\Benchmarks;

use JMToolkit\Csv\Mapping\ListOf;

// Classes the benchmark maps rows onto with Rows::into().

final readonly class Name
{
    public function __construct(public string $first, public string $last)
    {
    }
}

final readonly class Email
{
    public function __construct(public string $work, public string $home)
    {
    }
}

final readonly class Person
{
    public function __construct(public Name $name, public Email $email, public int $age)
    {
    }
}

final readonly class Address
{
    public function __construct(public string $street, public string $city, public string $zip)
    {
    }
}

final readonly class Record
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public int $id,
        public Person $user,
        public Address $address,
        #[ListOf('string')]
        public array $tags,
        public string $notes,
    ) {
    }
}
