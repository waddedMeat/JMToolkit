<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Column;

use JMToolkit\Exception;

/**
 * Only columns whose name matches a regular expression are used. Names are
 * matched after the column map is applied.
 *
 * <code>
 * new PatternFilter('/^user\./')     // every user.* column
 * new PatternFilter('/\.(id|name)$/') // every column ending in .id or .name
 * </code>
 */
final readonly class PatternFilter implements ColumnFilter
{
    /**
     * @param non-empty-string $pattern a PCRE pattern, delimiters included
     *
     * @throws Exception when the pattern is not a valid regular expression
     */
    public function __construct(private string $pattern)
    {
        $error = null;
        set_error_handler(static function (int $_, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $valid = preg_match($pattern, '') !== false;
        } finally {
            restore_error_handler();
        }

        if (!$valid) {
            throw new Exception(sprintf(
                'Invalid pattern "%s": %s',
                $pattern,
                // preg_match() reports compile errors as a warning, run-time ones through preg_last_error_msg().
                $error === null ? preg_last_error_msg() : preg_replace('/^preg_match\(\): /', '', $error),
            ));
        }
    }

    #[\Override]
    public function accepts(string $name): bool
    {
        return preg_match($this->pattern, $name) === 1;
    }
}
