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

```php
use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;

foreach (new Parser('users.csv') as $row) {
    echo $row['user']['name']['first'], PHP_EOL;
}

// Rows can be returned as objects instead of arrays.
$parser = new Parser('users.csv', ['row_format' => RowFormat::Object]);
foreach ($parser as $user) {
    echo $user->user->name->first, PHP_EOL;
}
```

Keys are 0-based row numbers. Breaking out of a loop and iterating again
continues with the next row.

Numeric path segments (`categories.0.name`, `categories.1.name`) produce
numerically indexed arrays. With `RowFormat::Object`, associative arrays
become `stdClass` objects and such lists stay arrays.

### Options

Options can be passed to the constructor or set with the fluent setters.
The column options (`column_map`, `whitelist`, `blacklist`) must be set before
the first row is read; `row_format` can be changed at any time.

| Option       | Setter            | Description                                                         |
|--------------|-------------------|---------------------------------------------------------------------|
| `row_format` | `setRowFormat()`  | `RowFormat::Array` (default) or `RowFormat::Object`.               |
| `column_map` | `setColumnMap()`  | Renames header columns, e.g. `['firstname' => 'user.name.first']`. |
| `whitelist`  | `setWhitelist()`  | Only these columns are used (matched after mapping).                |
| `blacklist`  | `setBlacklist()`  | These columns are skipped (matched after mapping).                  |
| `delimiter`  |                   | Field delimiter, default `,`.                                       |
| `enclosure`  |                   | Field enclosure, default `"`.                                       |
| `escape`     |                   | Escape character, default `""` (RFC 4180: quotes are escaped by doubling them). |

```php
$parser = new Parser('users.csv', [
    'column_map' => ['firstname' => 'user.name.first'],
    'blacklist' => ['user.password'],
    'delimiter' => ';',
]);
```

`setStream()` reads from an already open stream such as `php://stdin`.
`$parser->rowsFetched` holds the number of rows returned so far.

All errors (missing file, missing header row, rows with the wrong number of
columns, conflicting column names, unknown options) throw
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

## Upgrading from 0.x

- Classes are namespaced and autoloaded with Composer: `JMToolkit_CSV_Parser`
  is now `JMToolkit\Csv\Parser`, and `JMToolkit_Exception` is now
  `JMToolkit\Exception`. The old names are no longer available.
- `getNextArray()` and `getNextObject()` are replaced by iterating over the
  parser with `foreach`. Use the `row_format` option (or `setRowFormat()`)
  to choose between arrays and objects.
- Blank lines are skipped instead of raising a column count error.
- Backslash is no longer an escape character by default. Pass
  `['escape' => '\\']` to restore the old behaviour.
- Duplicate column names, and columns that would be both a value and a parent
  (`user` and `user.name`), now throw instead of silently overwriting data.
- The parser is `final` and its internals are private.

## Development

```sh
composer install
composer test
composer analyse
```
