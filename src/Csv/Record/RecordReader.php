<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Record;

/**
 * A source of CSV records.
 *
 * @internal
 */
interface RecordReader
{
    /**
     * Returns the next record, [null] for a blank line, or null when there
     * are no more records.
     *
     * @return non-empty-list<string|null>|null
     */
    public function read(): ?array;
}
