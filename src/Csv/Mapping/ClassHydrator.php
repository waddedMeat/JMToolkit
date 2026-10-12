<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Mapping;

use JMToolkit\Csv\Row\RowHydrator;
use JMToolkit\Csv\Row\Schema;

/**
 * Builds rows as instances of a class, through its constructor.
 *
 * @internal
 *
 * @template T of object
 */
final readonly class ClassHydrator implements RowHydrator
{
    /** @var \Closure(list<string|null>): T */
    private \Closure $build;

    /**
     * @param class-string<T> $class
     *
     * @throws MappingException when the class cannot be built from the schema's columns
     */
    public function __construct(Schema $schema, string $class)
    {
        $this->build = Compiler::object($class, $schema->tree);
    }

    /**
     * @param list<string|null> $record
     *
     * @return T
     */
    #[\Override]
    public function hydrate(array $record): object
    {
        return ($this->build)($record);
    }
}
