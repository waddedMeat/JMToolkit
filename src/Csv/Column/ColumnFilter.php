<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

/**
 * Decides whether a column is used. Pass one to Parser's filter argument;
 * combine several with AllOf. Implement it to select columns by your own
 * rules, e.g. by pattern.
 */
interface ColumnFilter
{
    /**
     * @param string $name the column name, after the column map is applied
     */
    public function accepts(string $name): bool;
}
