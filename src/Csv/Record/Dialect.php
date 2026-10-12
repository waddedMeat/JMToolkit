<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Record;

use JMToolkit\Exception;

/**
 * The characters that structure a CSV file.
 *
 * @internal
 */
final readonly class Dialect
{
    /**
     * @param non-empty-string $delimiter
     * @param non-empty-string $enclosure
     * @param string $escape a single character, or "" for RFC 4180 (enclosures escaped by doubling them)
     */
    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $escape = '',
    ) {
        foreach (['delimiter' => $delimiter, 'enclosure' => $enclosure, 'escape' => $escape] as $name => $value) {
            if (strlen($value) !== 1 && !($name === 'escape' && $value === '')) {
                throw new Exception(sprintf('Option %s must be a single character', $name));
            }
        }
    }
}
