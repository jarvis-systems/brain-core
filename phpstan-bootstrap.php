<?php

declare(strict_types=1);

/**
 * PHPStan bootstrap: constants defined at runtime by Brain CLI entry point.
 *
 * OK/ERROR — exit codes used in CLI commands.
 * DS — DIRECTORY_SEPARATOR alias used throughout CLI and core.
 *
 * @see cli/brain (entry point defines these before any command runs)
 */
if (!defined('OK')) {
    define('OK', 0);
}
if (!defined('ERROR')) {
    define('ERROR', 1);
}
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
