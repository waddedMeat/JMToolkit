<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

/**
 * Decides which header columns are used and what they are called.
 *
 * @internal
 */
final readonly class ColumnSelection
{
    /** @var list<ColumnFilter> */
    private array $filters;

    public function __construct(
        private ColumnMap $map = new ColumnMap(),
        ColumnFilter ...$filters,
    ) {
        $this->filters = array_values($filters);
    }

    /**
     * Applies the map, then every filter, to a header row.
     *
     * @param list<string|null> $headers
     *
     * @return array<int, string> column position => name, for the kept columns
     */
    public function resolve(array $headers): array
    {
        $columns = [];
        foreach ($headers as $position => $header) {
            $name = $this->map->nameOf((string) $header);

            foreach ($this->filters as $filter) {
                if (!$filter->accepts($name)) {
                    continue 2;
                }
            }

            $columns[$position] = $name;
        }

        return $columns;
    }
}
