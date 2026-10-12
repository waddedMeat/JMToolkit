<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Record;

/**
 * Stream filter that drops a UTF-8 byte-order mark from the start of a
 * stream. Used for streams that cannot seek back after peeking at their
 * first bytes.
 *
 * @internal
 */
final class SkipBomFilter extends \php_user_filter
{
    public const string NAME = 'jmtoolkit.skip-bom';

    public const string BOM = "\xEF\xBB\xBF";

    /** Leading bytes held back until it is known whether they are a byte-order mark. */
    private string $pending = '';

    private bool $decided = false;

    /**
     * @param resource $in
     * @param resource $out
     * @param int $consumed
     */
    #[\Override]
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $passedOn = false;

        while (($bucket = stream_bucket_make_writeable($in)) !== null) {
            $consumed += $bucket->datalen;

            if (!$this->decided) {
                $this->pending .= $bucket->data;
                // Wait for more data while the bytes so far could still be a byte-order mark.
                if (strlen($this->pending) < strlen(self::BOM) && str_starts_with(self::BOM, $this->pending)) {
                    continue;
                }
                $bucket->data = $this->release();
            }

            stream_bucket_append($out, $bucket);
            $passedOn = true;
        }

        if ($closing && !$this->decided && $this->pending !== '' && is_resource($this->stream)) {
            stream_bucket_append($out, stream_bucket_new($this->stream, $this->release()));
            $passedOn = true;
        }

        return $passedOn ? PSFS_PASS_ON : PSFS_FEED_ME;
    }

    private function release(): string
    {
        $this->decided = true;
        $data = str_starts_with($this->pending, self::BOM) ? substr($this->pending, strlen(self::BOM)) : $this->pending;
        $this->pending = '';

        return $data;
    }
}
