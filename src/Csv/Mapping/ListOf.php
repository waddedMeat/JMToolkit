<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Mapping;

/**
 * Builds an array parameter as a list of the given type, from numbered
 * columns such as "tags.0", "tags.1" or "items.0.name", "items.1.name":
 *
 * <code>
 * public function __construct(
 *     #[ListOf(Item::class)] public array $items,
 *     #[ListOf('int')] public array $scores,
 * )
 * </code>
 *
 * The type is a class, an enum, or one of int, float, bool and string.
 * Items whose cells are all empty are left out, so files can reserve more
 * numbered columns than every row uses.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class ListOf
{
    public function __construct(public string $type)
    {
    }
}
