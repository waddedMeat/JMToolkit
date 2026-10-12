<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

use JMToolkit\Exception;

/**
 * Renames header columns before they are split into key paths. Map a column
 * to a dot separated name to nest it.
 *
 * <code>
 * new ColumnMap()
 *     ->rename('firstname', 'user.name.first')
 *     ->rename('lastname', 'user.name.last');
 * </code>
 */
final readonly class ColumnMap
{
    /** @var array<string, string> original header => new name */
    private array $names;

    public function __construct()
    {
        /** @var array<string, string> $names (filled in by rename(), which clones with new names) */
        $names = [];
        $this->names = $names;
    }

    /**
     * Returns a copy of the map that also renames $from to $to.
     */
    public function rename(string $from, string $to): self
    {
        if (array_key_exists($from, $this->names)) {
            throw new Exception(sprintf('Column "%s" is already renamed to "%s"', $from, $this->names[$from]));
        }

        $names = $this->names;
        $names[$from] = $to;

        return clone($this, ['names' => $names]);
    }

    /**
     * The name to use for a header: its new name if it is renamed, otherwise
     * the header itself.
     */
    public function nameOf(string $header): string
    {
        return $this->names[$header] ?? $header;
    }
}
