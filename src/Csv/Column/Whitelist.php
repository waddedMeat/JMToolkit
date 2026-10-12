<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

/**
 * Only the listed columns are used. Names are matched after the column map
 * is applied.
 *
 * <code>
 * new Whitelist('user.name.first', 'user.age')
 * </code>
 */
final readonly class Whitelist implements ColumnFilter
{
    /** @var array<string, true> */
    private array $names;

    public function __construct(string ...$names)
    {
        $this->names = array_fill_keys($names, true);
    }

    #[\Override]
    public function accepts(string $name): bool
    {
        return isset($this->names[$name]);
    }
}
