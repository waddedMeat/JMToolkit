<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Mapping;

use JMToolkit\Csv\Row\ArrayHydrator;

/**
 * Compiles a class and the part of the schema it is built from into
 * closures that build objects from records. Reflection, attribute lookups
 * and type checks all happen here, once per file, so that building a row
 * is only reading cells, converting them and calling constructors.
 *
 * @internal
 */
final class Compiler
{
    private const array SCALARS = ['int', 'float', 'bool', 'string'];

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<array-key, mixed> $node schema subtree whose leaves are column positions
     * @param string $path dot separated column path of $node, for messages
     *
     * @return \Closure(list<string|null>): T
     *
     * @throws MappingException when the class cannot be built from these columns
     */
    public static function object(string $class, array $node, string $path = ''): \Closure
    {
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new MappingException(sprintf('%s cannot be instantiated', $class));
        }

        /** @var array<string, \Closure(list<string|null>): mixed> $arguments */
        $arguments = [];
        // Positional arguments are faster than named ones, and possible
        // unless a parameter left to its default is followed by another.
        $skipped = false;
        $positional = true;
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $target = sprintf('%s::$%s', $class, $name);
            if ($parameter->isVariadic()) {
                throw new MappingException(sprintf('%s is variadic, which cannot be mapped', $target));
            }

            $column = self::attribute($parameter, Column::class)->name ?? $name;
            $columnPath = $path === '' ? $column : $path . '.' . $column;
            $child = self::find($node, $column);

            if ($child === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $skipped = true;
                    continue;
                }
                if ($parameter->allowsNull()) {
                    $positional = $positional && !$skipped;
                    $arguments[$name] = static fn (): null => null;
                    continue;
                }
                throw new MappingException(sprintf('%s needs column "%s", which is not in the file', $target, $columnPath));
            }

            $positional = $positional && !$skipped;
            [$type, $nullable] = self::type($parameter, $target);
            $listOf = self::attribute($parameter, ListOf::class)?->type;

            if ($listOf === null) {
                $arguments[$name] = self::value($type, $nullable, $child, $columnPath, $target);
            } elseif ($type === 'array') {
                $arguments[$name] = self::list($listOf, $child, $columnPath, $target);
            } else {
                throw new MappingException(sprintf('%s has #[ListOf], so it must be typed array', $target));
            }
        }

        if ($positional) {
            $arguments = array_values($arguments);

            return static function (array $record) use ($class, $arguments): object {
                $values = [];
                foreach ($arguments as $argument) {
                    $values[] = $argument($record);
                }

                return new $class(...$values);
            };
        }

        return static function (array $record) use ($class, $arguments): object {
            $values = [];
            foreach ($arguments as $name => $argument) {
                $values[$name] = $argument($record);
            }

            return new $class(...$values);
        };
    }

    /**
     * @param int|array<array-key, mixed> $child a column position, or nested columns
     *
     * @return \Closure(list<string|null>): mixed
     */
    private static function value(?string $type, bool $nullable, int|array $child, string $path, string $target): \Closure
    {
        if (is_int($child)) {
            return self::cell($type, $nullable, $child, $path, $target);
        }

        if ($type === null || $type === 'array') {
            return static fn (array $record): array => ArrayHydrator::fill($child, $record);
        }

        if (
            in_array($type, self::SCALARS, true)
            || !class_exists($type)
            || enum_exists($type)
            || is_a($type, \DateTimeInterface::class, true)
        ) {
            throw new MappingException(sprintf('Column "%s" has nested columns, but %s is %s', $path, $target, $type));
        }

        $build = self::object($type, $child, $path);
        if (!$nullable) {
            return $build;
        }

        // A nullable object whose cells are all empty is null.
        $positions = self::positions($child);
        return static function (array $record) use ($build, $positions): ?object {
            foreach ($positions as $position) {
                if ($record[$position] !== '') {
                    return $build($record);
                }
            }

            return null;
        };
    }

    /**
     * @return \Closure(list<string|null>): mixed
     */
    private static function cell(?string $type, bool $nullable, int $position, string $path, string $target): \Closure
    {
        if ($type === null) {
            return static fn (array $record): ?string => $record[$position];
        }

        if ($type === 'string') {
            return $nullable
                ? static fn (array $record): ?string => $record[$position] === '' ? null : $record[$position]
                : static fn (array $record): string => (string) $record[$position];
        }

        $convert = self::converter($type, $path, $target);

        // An empty cell is null for a nullable parameter.
        return $nullable
            ? static fn (array $record): mixed => $record[$position] === '' ? null : $convert((string) $record[$position])
            : static fn (array $record): mixed => $convert((string) $record[$position]);
    }

    /**
     * @return \Closure(string): mixed
     */
    private static function converter(string $type, string $path, string $target): \Closure
    {
        $invalid = static fn (string $value): MappingException => MappingException::invalidValue($path, $value, $type, $target);

        if ($type === 'int') {
            return static fn (string $value): int => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
                ?? throw $invalid($value);
        }

        if ($type === 'float') {
            return static fn (string $value): float => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
                ?? throw $invalid($value);
        }

        if ($type === 'bool') {
            // Accepts 1/0, true/false, yes/no, on/off and the empty string (false).
            return static fn (string $value): bool => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                ?? throw $invalid($value);
        }

        if (is_a($type, \BackedEnum::class, true)) {
            $intBacked = (string) new \ReflectionEnum($type)->getBackingType() === 'int';

            return static function (string $value) use ($type, $intBacked, $invalid): \BackedEnum {
                $key = $intBacked ? filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : $value;

                return ($key === null ? null : $type::tryFrom($key)) ?? throw $invalid($value);
            };
        }

        if (is_a($type, \UnitEnum::class, true)) {
            // Pure enums are matched by case name.
            $cases = [];
            foreach ($type::cases() as $case) {
                $cases[$case->name] = $case;
            }

            return static fn (string $value): \UnitEnum => $cases[$value] ?? throw $invalid($value);
        }

        if (is_a($type, \DateTimeInterface::class, true)) {
            $class = $type === \DateTimeInterface::class ? \DateTimeImmutable::class : $type;
            if (!new \ReflectionClass($class)->isInstantiable()) {
                throw new MappingException(sprintf('%s is %s, which cannot be instantiated', $target, $type));
            }

            return static function (string $value) use ($class, $invalid): \DateTimeInterface {
                // An empty string would silently mean "now".
                if ($value === '') {
                    throw $invalid($value);
                }

                try {
                    return new $class($value);
                } catch (\Exception) {
                    throw $invalid($value);
                }
            };
        }

        throw new MappingException(sprintf('Column "%s" holds a single value, but %s is %s', $path, $target, $type));
    }

    /**
     * @param int|array<array-key, mixed> $child
     *
     * @return \Closure(list<string|null>): list<mixed>
     */
    private static function list(string $type, int|array $child, string $path, string $target): \Closure
    {
        $keys = is_array($child) ? array_keys($child) : [];
        if ($keys === [] || array_filter($keys, is_int(...)) !== $keys) {
            throw new MappingException(sprintf(
                '%s has #[ListOf], so column "%s" must have numbered columns such as "%2$s.0"',
                $target,
                $path,
            ));
        }
        if (!in_array($type, self::SCALARS, true) && !class_exists($type)) {
            throw new MappingException(sprintf('%s has #[ListOf(\'%s\')], which is not a class, enum or scalar type', $target, $type));
        }

        /** @var array<int, mixed> $child */
        ksort($child);

        $items = [];
        foreach ($child as $index => $item) {
            /** @var int|array<array-key, mixed> $item */
            $items[] = [self::value($type, false, $item, $path . '.' . $index, $target), self::positions($item)];
        }
        return static function (array $record) use ($items): array {
            $list = [];
            foreach ($items as [$build, $positions]) {
                // Items whose cells are all empty are left out.
                foreach ($positions as $position) {
                    if ($record[$position] !== '') {
                        $list[] = $build($record);
                        continue 2;
                    }
                }
            }

            return $list;
        };
    }

    /**
     * @return array{?string, bool} the type name (null when untyped or mixed) and whether it allows null
     */
    private static function type(\ReflectionParameter $parameter, string $target): array
    {
        $type = $parameter->getType();
        if ($type === null) {
            return [null, true];
        }

        if (!$type instanceof \ReflectionNamedType) {
            throw new MappingException(sprintf('%s has a union or intersection type, which cannot be mapped', $target));
        }

        $name = $type->getName();

        return match ($name) {
            'mixed' => [null, true],
            'self', 'static' => [$parameter->getDeclaringClass()?->getName(), $type->allowsNull()],
            default => [$name, $type->allowsNull()],
        };
    }

    /**
     * Follows a dot separated column name into the schema.
     *
     * @param array<array-key, mixed> $node
     *
     * @return int|array<array-key, mixed>|null
     */
    private static function find(array $node, string $column): int|array|null
    {
        $child = $node;
        foreach (explode('.', $column) as $key) {
            if (!is_array($child) || !array_key_exists($key, $child)) {
                return null;
            }
            $child = $child[$key];
        }

        /** @var int|array<array-key, mixed> $child */
        return $child;
    }

    /**
     * @param int|array<array-key, mixed> $node
     *
     * @return list<int> the column positions under a schema node
     */
    private static function positions(int|array $node): array
    {
        if (is_int($node)) {
            return [$node];
        }

        $positions = [];
        foreach ($node as $child) {
            /** @var int|array<array-key, mixed> $child */
            $positions = [...$positions, ...self::positions($child)];
        }

        return $positions;
    }

    /**
     * @template A of object
     *
     * @param class-string<A> $attribute
     *
     * @return A|null
     */
    private static function attribute(\ReflectionParameter $parameter, string $attribute): ?object
    {
        $attributes = $parameter->getAttributes($attribute);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
