<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Record;

use JMToolkit\Csv\Record\Dialect;
use JMToolkit\Csv\Record\SkipBomFilter;
use JMToolkit\Csv\Record\StreamReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * StreamReader must produce exactly what fgetcsv() produces, so every case here
 * compares the two on the same input.
 */
#[CoversClass(StreamReader::class)]
#[CoversClass(SkipBomFilter::class)]
final class StreamReaderTest extends TestCase
{
    private const string BOM = "\xEF\xBB\xBF";

    /**
     * @return iterable<string, array{string}>
     */
    public static function edgeCases(): iterable
    {
        $cases = [
            'plain' => "a,b,c\n1,2,3\n",
            'no trailing newline' => "a,b\n1,2",
            'crlf' => "a,b\r\n1,2\r\n",
            'cr at end of file' => "a,b\r",
            'doubled line endings' => "a,b\r\r\n1,2\n\r\n",
            'blank lines' => "\n\r\n\na\n\n",
            'whitespace only line' => "  \n\t\n",
            'empty fields' => ",,\n,\n",
            'enclosed' => "\"a\",\"b,c\",\"\"\n",
            'escaped enclosure' => "\"say \"\"hi\"\"\",x\n",
            'only escaped enclosures' => "\"\"\"\"\"\",\"\"\"\"\n",
            'multiline field' => "\"line 1\nline 2\",x\ny,z\n",
            'multiline crlf field' => "\"line 1\r\nline 2\"\r\n",
            'unterminated enclosure' => "a,\"b\nc\n",
            'unterminated at end of file' => "\"abc",
            'text after closing enclosure' => "\"ab\"cd,e\n",
            'enclosure inside unenclosed field' => "ab\"cd,e\nx\"\"y\n",
            'spaces before enclosure' => "a,  \"b\" ,c\n",
            'spaces around fields' => " a , b \n",
            'backslashes' => "\"C:\\dir\\\",x\n\"a\\\"b\"\n",
            'carriage return inside field' => "a\rb,c\n\"x\ry\",z\n",
            'nul byte' => "a\0b,\"c\0d\"\n",
            'utf-8' => "é,\"ü,ö\"\n",
            'invalid utf-8' => "\xE9t\xE9,\"\xFF\"\n",
            'single enclosure' => "\"\n",
            'delimiter only' => ",\n",
        ];

        foreach ($cases as $name => $csv) {
            yield $name => [$csv];
        }
    }

    #[DataProvider('edgeCases')]
    public function testMatchesFgetcsv(string $csv): void
    {
        self::assertSame(self::expected($csv), self::actual($csv));
    }

    #[DataProvider('edgeCases')]
    public function testMatchesFgetcsvWithCustomDelimiterAndEnclosure(string $csv): void
    {
        $csv = strtr($csv, [',' => ';', '"' => "'"]);

        self::assertSame(self::expected($csv, ';', "'"), self::actual($csv, ';', "'"));
    }

    #[DataProvider('edgeCases')]
    public function testMatchesFgetcsvWithEscapeCharacter(string $csv): void
    {
        self::assertSame(self::expected($csv, escape: '\\'), self::actual($csv, escape: '\\'));
    }

    #[DataProvider('edgeCases')]
    public function testMatchesFgetcsvOnNonSeekableStream(string $csv): void
    {
        [$reader, $writer] = self::socketPair();
        fwrite($writer, $csv);
        fclose($writer);

        self::assertFalse(stream_get_meta_data($reader)['seekable']);
        self::assertSame(self::expected($csv), self::readAll(new StreamReader($reader)));
        fclose($reader);
    }

    #[DataProvider('edgeCases')]
    public function testSkipsByteOrderMark(string $csv): void
    {
        self::assertSame(self::expected($csv), self::actual(self::BOM . $csv));
        self::assertSame(self::expected($csv, escape: '\\'), self::actual(self::BOM . $csv, escape: '\\'));
    }

    #[DataProvider('edgeCases')]
    public function testSkipsByteOrderMarkOnNonSeekableStream(string $csv): void
    {
        [$reader, $writer] = self::socketPair();
        fwrite($writer, self::BOM . $csv);
        fclose($writer);

        self::assertSame(self::expected($csv), self::readAll(new StreamReader($reader)));
        fclose($reader);
    }

    public function testSkipsByteOrderMarkBeforeEnclosedField(): void
    {
        self::assertSame([['id', 'name'], ['1', 'Ann']], self::actual(self::BOM . "\"id\",\"name\"\n1,Ann\n"));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function byteOrderMarkLookalikes(): iterable
    {
        yield 'only the first of two marks' => [self::BOM . self::BOM . "a\n", self::BOM . "a\n"];
        yield 'partial mark' => ["\xEF\xBBa\n", "\xEF\xBBa\n"];
        yield 'partial mark only' => ["\xEF\xBB", "\xEF\xBB"];
        yield 'mark only' => [self::BOM, ''];
        yield 'mark after the start' => ["a\n" . self::BOM . "b\n", "a\n" . self::BOM . "b\n"];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('byteOrderMarkLookalikes')]
    public function testOnlySkipsALeadingByteOrderMark(string $csv, string $withoutMark): void
    {
        self::assertSame(self::expected($withoutMark), self::actual($csv));
    }

    #[DataProvider('byteOrderMarkLookalikes')]
    public function testFilterHandlesMarkSplitAcrossReads(string $csv, string $withoutMark): void
    {
        $stream = self::memory($csv);
        stream_set_chunk_size($stream, 1);
        if (!in_array(SkipBomFilter::NAME, stream_get_filters(), true)) {
            stream_filter_register(SkipBomFilter::NAME, SkipBomFilter::class);
        }
        stream_filter_append($stream, SkipBomFilter::NAME, STREAM_FILTER_READ);

        self::assertSame($withoutMark, stream_get_contents($stream));
        fclose($stream);
    }

    public function testReadsFromCurrentPositionOfStream(): void
    {
        $stream = self::memory("skip me\n" . self::BOM . "a,b\n");
        fgets($stream);

        self::assertSame([['a', 'b']], self::readAll(new StreamReader($stream)));
        fclose($stream);
    }

    public function testFilterIsRemovedFromCallersStream(): void
    {
        [$reader, $writer] = self::socketPair();
        fwrite($writer, "a\n" . self::BOM . "b\n");
        fclose($writer);

        $csv = new StreamReader($reader);
        self::assertSame(['a'], $csv->read());
        unset($csv);

        // A mark later in the stream must reach the caller untouched.
        self::assertSame(self::BOM . "b\n", stream_get_contents($reader));
        fclose($reader);
    }

    public function testMatchesFgetcsvOnRandomInput(): void
    {
        $alphabet = ['a', 'b', ' ', ',', ',', '"', '"', '"', "\n", "\n", "\r", '\\', "\xC3\xA9", ';', "\t"];
        $random = new \Random\Randomizer(new \Random\Engine\Mt19937(20111028));

        for ($case = 0; $case < 5000; $case++) {
            $csv = '';
            $length = $random->getInt(0, 40);
            for ($i = 0; $i < $length; $i++) {
                $csv .= $alphabet[$random->getInt(0, count($alphabet) - 1)];
            }

            self::assertSame(self::expected($csv), self::actual($csv), sprintf('Input: %s', json_encode($csv)));
        }
    }

    /**
     * @param non-empty-string $delimiter
     * @param non-empty-string $enclosure
     *
     * @return list<non-empty-list<string|null>>
     */
    private static function expected(
        string $csv,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '',
    ): array {
        $stream = self::memory($csv);
        $records = [];
        while (($record = fgetcsv($stream, null, $delimiter, $enclosure, $escape)) !== false) {
            $records[] = $record;
        }
        fclose($stream);

        return $records;
    }

    /**
     * @param non-empty-string $delimiter
     * @param non-empty-string $enclosure
     *
     * @return list<non-empty-list<string|null>>
     */
    private static function actual(
        string $csv,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '',
    ): array {
        $stream = self::memory($csv);
        $records = self::readAll(new StreamReader($stream, new Dialect($delimiter, $enclosure, $escape)));
        fclose($stream);

        return $records;
    }

    /**
     * @return list<non-empty-list<string|null>>
     */
    private static function readAll(StreamReader $reader): array
    {
        $records = [];
        while (($record = $reader->read()) !== null) {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return array{resource, resource} a non-seekable reader and its writer
     */
    private static function socketPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pair);

        return [$pair[0], $pair[1]];
    }

    /**
     * @return resource
     */
    private static function memory(string $csv)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $csv);
        rewind($stream);

        return $stream;
    }
}
