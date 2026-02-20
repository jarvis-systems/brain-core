<?php

declare(strict_types=1);

namespace BrainCore\Mcp\Traits;

use BrainCore\Mcp\McpSchemaValidator;

trait McpSchemaTrait
{
    public static function schema(): array
    {
        $schemaClass = static::getSchemaClass();
        if (class_exists($schemaClass) && method_exists($schemaClass, 'get')) {
            return $schemaClass::get();
        }
        return [];
    }

    protected static function getSchemaClass(): string
    {
        return '';
    }

    public static function callValidatedJson(string $method, array $args = [], string $mode = 'standard'): string
    {
        McpSchemaValidator::validate($method, $args, static::schema(), $mode);
        return static::callJson($method, $args);
    }
}
