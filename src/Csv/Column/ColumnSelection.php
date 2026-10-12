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
    public function __construct(
        private ColumnMap $map = new ColumnMap(),
        private ?ColumnFilter $filter = null,
    ) {
    }

    /**
     * Applies the map, then the filter, to a header row.
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

            if ($this->filter === null || $this->filter->accepts($name)) {
                $columns[$position] = $name;
            }
        }

        return $columns;
    }
}
