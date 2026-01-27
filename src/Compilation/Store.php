<?php

declare(strict_types=1);

namespace BrainCore\Compilation;

use BrainCore\Compilation\Traits\CompileStandartsTrait;
use BrainCore\MD;

/**
 * Variable storage operators for pseudo-syntax.
 */
class Store
{
    use CompileStandartsTrait;

    /**
     * Store value with assignment.
     * Format: STORE-AS($VAR = value)
     */
    public static function as(string $name, ...$appropriate): string
    {
        $varName = static::var($name);

        if (empty($appropriate)) {
            return 'STORE-AS(' . $varName . ')';
        }

        $values = array_map(function ($item) {
            if (is_array($item)) {
                return static::flattenArray($item);
            }
            if (is_string($item)) {
                return $item;
            }
            return static::exportValue($item);
        }, $appropriate);

        return 'STORE-AS(' . $varName . ' = ' . implode(' + ', $values) . ')';
    }

    /**
     * Get stored value.
     * Format: STORE-GET($VAR)
     */
    public static function get(string $name): string
    {
        return 'STORE-GET(' . static::var($name) . ')';
    }

    /**
     * Format variable reference.
     * Format: $VAR_NAME
     */
    public static function var(string $name): string
    {
        return '$' . strtoupper(ltrim($name, '$'));
    }

    /**
     * Export value for pseudo-syntax.
     */
    private static function exportValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        return '\'' . $value . '\'';
    }
}
