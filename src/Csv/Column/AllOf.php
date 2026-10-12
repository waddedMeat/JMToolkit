<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

/**
 * Combines filters: a column is used only if every filter accepts it.
 *
 * <code>
 * new AllOf(new Allowlist('user.name', 'user.password'), new Denylist('user.password'))
 * </code>
 */
final readonly class AllOf implements ColumnFilter
{
    /** @var list<ColumnFilter> */
    private array $filters;

    public function __construct(ColumnFilter ...$filters)
    {
        $this->filters = array_values($filters);
    }

    #[\Override]
    public function accepts(string $name): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->accepts($name)) {
                return false;
            }
        }

        return true;
    }
}
