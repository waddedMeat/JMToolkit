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

$parser = new Parser('users.csv');

// Iterate over every row...
foreach ($parser as $row) {
    echo $row['user']['name']['first'], PHP_EOL;
}

// ...or pull rows one at a time. Both methods return null at end of file.
$parser->setFile('users.csv');
while (($user = $parser->getNextObject()) !== null) {
    echo $user->user->name->first, PHP_EOL;
}
```

Numeric path segments (`categories.0.name`, `categories.1.name`) produce
numerically indexed arrays. `getNextObject()` turns associative arrays into
`stdClass` objects and keeps such lists as arrays.

### Options

Options can be passed to the constructor or set with the fluent setters
before the first row is read.

| Option       | Setter            | Description                                                         |
|--------------|-------------------|---------------------------------------------------------------------|
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

## Upgrading from 0.x

- Classes are namespaced and autoloaded with Composer: `JMToolkit_CSV_Parser`
  is now `JMToolkit\Csv\Parser`, and `JMToolkit_Exception` is now
  `JMToolkit\Exception`. The old names still work as aliases.
- `getNextArray()` and `getNextObject()` return `null` instead of `false`
  at end of file.
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
