<?php

declare(strict_types=1);

namespace BrainCore\Abstracts;

use BrainCore\Compilation\Traits\CompileStandardsTrait;

abstract class ToolAbstract
{
    use CompileStandardsTrait;

    abstract public static function name(): string;

    public static function describe(string|array $command, ...$steps): string
    {
        return static::generateOperator(
            static::name(),
            $command,
            $steps,
            concatBody: true
        );
    }

    public static function call(...$parameters): string
    {
        return static::generateOperator(
            static::name(),
            static::parametersToString($parameters)
        );
    }
}
