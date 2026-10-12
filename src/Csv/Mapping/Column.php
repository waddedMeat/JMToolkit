<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Mapping;

/**
 * Reads a constructor parameter from a different column than its name.
 * Dot separated names reach into nested columns, which flattens them:
 *
 * <code>
 * public function __construct(
 *     #[Column('email.work')] public string $email,
 * )
 * </code>
 *
 * The name is relative to the object being built, and matched after the
 * parser's column map is applied.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Column
{
    public function __construct(public string $name)
    {
    }
}
