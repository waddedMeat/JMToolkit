<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Record;

use JMToolkit\Exception;

/**
 * Skips blank lines and requires every record to have as many columns as the
 * first one.
 *
 * @internal
 */
final class ValidatingReader implements RecordReader
{
    /** Number of columns in the first record; 0 until it has been read. */
    private int $width = 0;

    /** Number of records read so far, used in error messages. */
    private int $recordNumber = 0;

    public function __construct(private readonly RecordReader $reader)
    {
    }

    /**
     * @return non-empty-list<string|null>|null
     */
    #[\Override]
    public function read(): ?array
    {
        do {
            $record = $this->reader->read();
            if ($record === null) {
                return null;
            }
            $this->recordNumber++;
        } while ($record === [null]);

        $width = count($record);
        if ($this->width === 0) {
            $this->width = $width;
        } elseif ($width !== $this->width) {
            throw new Exception(sprintf(
                'Invalid file: record %d has %d columns, expected %d',
                $this->recordNumber,
                $width,
                $this->width,
            ));
        }

        return $record;
    }
}
