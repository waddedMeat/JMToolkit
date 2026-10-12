<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping\Fixture;

use JMToolkit\Csv\Mapping\Column;
use JMToolkit\Csv\Mapping\ListOf;

final readonly class User
{
    /**
     * @param list<Tag> $tags
     */
    public function __construct(
        public int $id,
        public Name $name,
        #[Column('email.work')]
        public string $email,
        public ?Address $address,
        #[ListOf(Tag::class)]
        public array $tags,
        public Status $status,
        public ?\DateTimeImmutable $joined,
        public bool $active = true,
    ) {
    }
}
