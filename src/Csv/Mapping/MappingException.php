<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Mapping;

use JMToolkit\Exception;

/**
 * A row could not be mapped onto a class: either the class does not fit
 * the file (thrown when the header is read), or a value does not fit its
 * type (thrown for the row it is in).
 */
final class MappingException extends Exception
{
    /**
     * @internal
     */
    public static function invalidValue(string $column, string $value, string $type, string $target): self
    {
        return new self(sprintf('column "%s": "%s" is not a valid %s for %s', $column, $value, $type, $target));
    }

    /**
     * @internal adds the 1-based number of the row being mapped
     */
    public function inRow(int $row): self
    {
        return new self(sprintf('Row %d, %s', $row, $this->getMessage()), previous: $this);
    }
}
