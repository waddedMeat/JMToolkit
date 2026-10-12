# JMToolkit

Parses CSV files into nested arrays or objects, using the header row to define
keys with dot (`.`) separated namespacing.

```csv
user.name.first,user.name.last,user.age
James,Moran,37
```

```php
['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37']]
```

Requires PHP 8.5 or newer.

## Installation

```sh
composer require jmoran/jm-toolkit
```

## Usage

A `Parser` holds the configuration. Each call to `parseFile()` or
`parseStream()` returns an independent `Rows` that you iterate over:

```php
use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;

$parser = new Parser();

foreach ($parser->parseFile('users.csv') as $row) {
    echo $row['user']['name']['first'], PHP_EOL;
}

// Rows can be returned as objects instead of arrays.
$parser = new Parser(rowFormat: RowFormat::Object);

foreach ($parser->parseFile('users.csv') as $user) {
    echo $user->user->name->first, PHP_EOL;
}
```

`Rows` reads the file forward, one row at a time. Keys are 0-based row
numbers, and breaking out of a loop and iterating again continues with the
next row. `$rows->headers` holds the header row as it appears in the file,
and `$rows->rowsFetched` the number of rows returned so far.

`parseStream()` reads from an already open stream such as `php://stdin`;
the caller remains responsible for closing it.

Numeric path segments (`categories.0.name`, `categories.1.name`) produce
numerically indexed arrays. With `RowFormat::Object`, associative arrays
become `stdClass` objects and such lists stay arrays.

### Options

All options are named constructor arguments:

| Argument    | Description                                                                     |
|-------------|---------------------------------------------------------------------------------|
| `rowFormat` | `RowFormat::Array` (default) or `RowFormat::Object`.                            |
| `columnMap` | A `ColumnMap` that renames header columns before they are nested.               |
| `filter`    | A `ColumnFilter` that decides which columns are used (matched after mapping).   |
| `delimiter` | Field delimiter, default `,`.                                                   |
| `enclosure` | Field enclosure, default `"`.                                                   |
| `escape`    | Escape character, default `""` (RFC 4180: quotes are escaped by doubling them). |

```php
use JMToolkit\Csv\Column\Denylist;
use JMToolkit\Csv\Column\ColumnMap;

$parser = new Parser(
    columnMap: new ColumnMap()
        ->rename('firstname', 'user.name.first')
        ->rename('lastname', 'user.name.last'),
    filter: new Denylist('user.password'),
    delimiter: ';',
);
```

`ColumnMap::rename()` returns a new map, so a map can be shared and extended
safely; renaming the same column twice throws.

### Choosing columns

Pass one of these as `filter` (names are matched after the column map is
applied):

| Filter                               | Uses                                                   |
|--------------------------------------|--------------------------------------------------------|
| `new Allowlist('a', 'b')`            | only the listed columns                                |
| `new Denylist('a', 'b')`             | every column except the listed ones                    |
| `new PatternFilter('/^user\./')`     | only columns whose name matches the regular expression |
| `new AllOf($filter, ...)`            | only columns that every given filter accepts           |

```php
use JMToolkit\Csv\Column\AllOf;
use JMToolkit\Csv\Column\PatternFilter;

// Every user.* column except the password.
new Parser(filter: new AllOf(
    new PatternFilter('/^user\./'),
    new Denylist('user.password'),
));
```

An invalid pattern throws `JMToolkit\Exception` when the `PatternFilter` is
created.

To select columns by your own rules, implement `ColumnFilter`:

```php
use JMToolkit\Csv\Column\ColumnFilter;

/** Skips columns at any level whose name starts with an underscore. */
final readonly class SkipPrivate implements ColumnFilter
{
    public function accepts(string $name): bool
    {
        return !str_starts_with($name, '_') && !str_contains($name, '._');
    }
}

new Parser(filter: new SkipPrivate());
```

All errors (missing file, missing header row, rows with the wrong number of
columns, conflicting column names, invalid characters or patterns) throw
`JMToolkit\Exception`.

## Performance

Files are streamed one record at a time, so memory use stays constant no
matter how large the file is. The header is compiled once into the shape of
a row, and well-formed lines are tokenized without going through
`fgetcsv()`, which is the slowest part of reading CSV in PHP. Lines that need
its full rules (fields spanning several lines, stray quotes, an `escape`
character, or a stream that cannot seek such as `php://stdin`) still use it,
so results are identical either way.

```sh
php benchmarks/parse.php 200000 array
php benchmarks/parse.php 200000 object
```

## Architecture

`Parser` holds the configuration and `Rows` coordinates reading one file;
each concern lives in its own internal class:

| Class                            | Responsibility                                                         |
|----------------------------------|------------------------------------------------------------------------|
| `Csv\Record\Dialect`             | Delimiter, enclosure and escape characters (validated).                |
| `Csv\Record\StreamReader`        | Tokenizes records from a stream, closing files it opened itself.       |
| `Csv\Record\ValidatingReader`    | Skips blank lines and enforces a consistent number of columns.         |
| `Csv\Column\ColumnSelection`     | Applies the `ColumnMap`, then the `ColumnFilter`, to the header.        |
| `Csv\Row\Schema`                 | Compiles the header into the nested shape of a row, rejecting clashes. |
| `Csv\Row\ArrayHydrator`, `ObjectHydrator` | Fill that shape from a record, one per `RowFormat`.          |

Readers implement `RecordReader` and hydrators implement `RowHydrator`, so
`Rows` depends only on those interfaces.

## Upgrading from 0.x

- Classes are namespaced and autoloaded with Composer: `JMToolkit_CSV_Parser`
  is now `JMToolkit\Csv\Parser`, and `JMToolkit_Exception` is now
  `JMToolkit\Exception`. The old names are no longer available.
- `Parser` only holds configuration, passed as named constructor arguments
  instead of an options array; the setters are gone. The column map is a
  `ColumnMap` object. `setWhitelist()` and `setBlacklist()` are replaced by
  `Allowlist` and `Denylist` objects passed as `filter` (combine them with
  `AllOf`).
- `setFile()`, `getNextArray()` and `getNextObject()` are replaced by
  iterating over `$parser->parseFile($path)` (or `parseStream()`) with
  `foreach`. The `rowFormat` argument chooses between arrays and objects.
- Blank lines are skipped instead of raising a column count error.
- Backslash is no longer an escape character by default. Pass
  `escape: '\\'` to restore the old behaviour.
- Duplicate column names, and columns that would be both a value and a parent
  (`user` and `user.name`), now throw instead of silently overwriting data.
- The parser is `final` and its internals are private.

## Development

```sh
composer install
composer test
composer analyse
```
