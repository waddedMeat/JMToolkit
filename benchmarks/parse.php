<?php

declare(strict_types=1);

/**
 * Usage: php benchmarks/parse.php [rows=200000] [array|object|class]
 *
 * Generates a temporary CSV with nested, list-like and flat columns, then
 * reports the time and peak memory needed to iterate over it.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/Fixture.php';

use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;

$rows = (int) ($argv[1] ?? 200_000);
$mode = $argv[2] ?? 'array';
$format = $mode === 'object' ? RowFormat::Object : RowFormat::Array;

$headers = [
    'id', 'user.name.first', 'user.name.last', 'user.email.work', 'user.email.home', 'user.age',
    'address.street', 'address.city', 'address.zip', 'tags.0', 'tags.1', 'tags.2', 'notes',
];

$path = tempnam(sys_get_temp_dir(), 'jmtoolkit-bench');
$out = $path === false ? false : fopen($path, 'w');
if ($path === false || $out === false) {
    fwrite(STDERR, "Unable to create a temporary file\n");
    exit(1);
}
fputcsv($out, $headers, escape: '');
for ($i = 0; $i < $rows; $i++) {
    fputcsv($out, [
        $i, 'First' . $i, 'Last' . $i, "w$i@example.com", "h$i@example.com", $i % 90,
        "$i Main St", 'Springfield', sprintf('%05d', $i % 100000), 'a', 'b', 'c', 'Some "quoted", text',
    ], escape: '');
}
fclose($out);

gc_collect_cycles();
$baseline = memory_get_usage();
memory_reset_peak_usage();

$start = hrtime(true);
$count = 0;
$rows = new Parser(rowFormat: $format)->parseFile($path);
foreach ($mode === 'class' ? $rows->into(JMToolkit\Benchmarks\Record::class) : $rows as $row) {
    $count++;
}
$elapsed = (hrtime(true) - $start) / 1e9;

unlink($path);

printf(
    "%s rows (%s): %.3f s, %s rows/s, peak memory +%.1f KiB\n",
    number_format($count),
    $mode === 'class' ? 'class' : strtolower($format->name),
    $elapsed,
    number_format($count / $elapsed),
    (memory_get_peak_usage() - $baseline) / 1024,
);
