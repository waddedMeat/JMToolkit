<?php

declare(strict_types=1);

/**
 * Lazily maps the pre-namespace class names (JMToolkit_CSV_Parser,
 * JMToolkit_Exception) onto the namespaced classes so existing code keeps
 * working. The aliases are only created when the old name is first used.
 */
spl_autoload_register(static function (string $class): void {
    $target = match ($class) {
        'JMToolkit_CSV_Parser' => JMToolkit\Csv\Parser::class,
        'JMToolkit_Exception' => JMToolkit\Exception::class,
        default => null,
    };

    if ($target !== null) {
        class_alias($target, $class);
    }
});
