<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

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
}
