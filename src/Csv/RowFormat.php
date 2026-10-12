<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

use JMToolkit\Csv\Row\ArrayHydrator;
use JMToolkit\Csv\Row\ObjectHydrator;
use JMToolkit\Csv\Row\RowHydrator;
use JMToolkit\Csv\Row\Schema;

/**
 * The shape of each row produced when iterating over a Parser.
 */
enum RowFormat
{
    /** Nested associative arrays. */
    case Array;

    /**
     * Nested stdClass objects. List-like columns such as "items.0",
     * "items.1" stay arrays (the same shape json_decode() would produce).
     */
    case Object;

    /**
     * @internal
     */
    public function hydrator(Schema $schema): RowHydrator
    {
        return match ($this) {
            self::Array => new ArrayHydrator($schema),
            self::Object => new ObjectHydrator($schema),
        };
    }
}
