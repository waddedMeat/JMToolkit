<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Row;

/**
 * Turns a record into a row of a particular format.
 *
 * @internal
 */
interface RowHydrator
{
    /**
     * @param list<string|null> $record
     *
     * @return array<array-key, mixed>|object
     */
    public function hydrate(array $record): array|object;
}
