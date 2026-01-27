<?php

declare(strict_types=1);

namespace BrainCore\Compilation\Traits;

use BrainCore\MD;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Standard compilation patterns for pseudo-syntax generation.
 *
 * Uses MD constants for consistent formatting across all compiled output.
 * Arrows (→) indicate flow/causality - research shows this improves model comprehension.
 */
trait CompileStandartsTrait
{
    protected static function generateOperator(
        string $name,
        array|string $arguments = [],
        array|string $body = [],
        bool $hybridBody = false,
        bool $concatBody = false,
        bool $separatedArgs = false,
    ): string {

        return $name
            . static::generateOperatorArguments($arguments, $separatedArgs)
            . (! empty($body = static::generateOperatorBody($body, $hybridBody, $concatBody, $name))
                ? ' ' . MD::ARROW . ' ' . $body : '');
    }

    protected static function generateOperatorArguments(array|string $arguments = [], bool $separated = false): string
    {
        $arguments = is_string($arguments) ? [$arguments] : array_filter($arguments);

        // Flatten nested arrays to strings
        foreach ($arguments as $index => $arg) {
            if (is_array($arg)) {
                $arguments[$index] = static::flattenArray($arg);
            } elseif (!is_string($arg)) {
                try {
                    $arguments[$index] = VarExporter::export($arg);
                } catch (\Throwable) {
                    $arguments[$index] = '[unserializable]';
                }
            }
        }

        return count($arguments) > 0 ? "(" . implode($separated ? ' && ' : ' ', $arguments) . ")" : '';
    }

    protected static function generateOperatorBody(array|string $body = [], bool $hybrid = false, bool $concat = false, string|null $name = null): string
    {
        $body = is_string($body) ? [$body] : array_filter($body);
        if ($concat) {
            foreach ($body as $index => $bodyData) {
                if (is_array($bodyData)) {
                    $body[$index] = static::concat($bodyData);
                }
            }
        }
        return count($body) > 0
            ? (
                ($hybrid ? '' : '[') .
                static::generateOperatorBodyLine($body) .
                ($hybrid ? '' : ']') .
                ($name ? ' ' . MD::ARROW . " END-$name" : '')
            )
            : '';
    }

    protected static function generateOperatorBodyLine(array $args = []): string
    {
        foreach ($args as $index => $arg) {
            if (is_array($arg)) {
                $args[$index] = '(' . implode(' + ', $arg) . ')';
            }
        }

        return implode(' ' . MD::ARROW . ' ', $args);
    }

    protected static function concat(...$args): string
    {
        foreach ($args as $index => $arg) {
            if (is_array($arg)) {
                $args[$index] = static::concat(...$arg);
            } elseif (! is_string($arg)) {
                try {
                    $args[$index] = VarExporter::export($arg);
                } catch (\Throwable) {
                    $args[$index] = '[unserializable]';
                }
            }
        }
        return trim(implode(' ', array_filter($args)));
    }

    protected static function flattenArray(array $array, string $separator = ' '): string
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $result[] = static::flattenArray($item, $separator);
            } elseif (is_string($item)) {
                $result[] = $item;
            } else {
                try {
                    $result[] = VarExporter::export($item);
                } catch (\Throwable) {
                    $result[] = '[unserializable]';
                }
            }
        }
        return implode($separator, $result);
    }

    protected static function exportedArray(array $array, string $separator = ' '): string
    {
        $result = [];
        foreach ($array as $item) {
            try {
                $result[] = VarExporter::export($item);
            } catch (\Throwable) {
                $result[] = '[unserializable]';
            }
        }
        return implode($separator, $result);
    }

    protected static function parametersToString(array $parameters, string $separator = ', ', bool $exportOnlyNonString = false): string
    {
        foreach ($parameters as $key => $value) {
            try {
                if (
                    is_string($value)
                    && ! str_contains($value, " ")
                    && strlen($value) < 255
                    && class_exists($value)
                    && method_exists($value, "id")
                ) {
                    $parameters[$key] = $value::id();
                } else {
                    if ($exportOnlyNonString) {
                        if (! is_string($value)) {
                            if (is_array($value)) {
                                $parameters[$key] = static::flattenArray($value);
                            } else {
                                $parameters[$key] = VarExporter::export($value);
                            }
                        }
                    } else {
                        $parameters[$key] = VarExporter::export($value);
                    }
                }
            } catch (\Throwable) {
                if ($exportOnlyNonString) {
                    $parameters[$key] = '[unserializable]';
                } else {
                    $parameters[$key] = '"[unserializable]"';
                }
            }
        }

        return implode($separator, $parameters);
    }
}
