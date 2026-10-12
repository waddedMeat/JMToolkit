<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

/**
 * Decides whether a column is used. Implemented by Whitelist and Blacklist.
 */
interface ColumnFilter
{
    /**
     * @param string $name the column name, after the column map is applied
     */
    public function accepts(string $name): bool;
}
