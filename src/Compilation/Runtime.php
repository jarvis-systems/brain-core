<?php

declare(strict_types=1);

namespace BrainCore\Compilation;

/**
 * The class of runtime variables.
 *
 * @method static string YEAR() Year of the current date.
 * @method static string MONTH() Month of the current date.
 * @method static string DAY() Day of the current date.
 * @method static string UNIQUE_ID() Unique ID of the current date.
 * @method static string DATE_TIME() Date and time of the current date.
 * @method static string DATE() Date of the current date.
 * @method static string TIME() Time of the current date.
 * @method static string AGENT() Agent name.
 * @method static string BRAIN_FILE() Brain file name.
 * @method static string MCP_FILE() MCP file name.
 */
class Runtime
{
    const PROJECT_DIRECTORY = '{{ PROJECT_DIRECTORY }}';
    const BRAIN_DIRECTORY = '{{ BRAIN_DIRECTORY }}';
    const NODE_DIRECTORY = '{{ NODE_DIRECTORY }}';
    const TIMESTAMP = '{{ TIMESTAMP }}';
    const DATE_TIME = '{{ DATE_TIME }}';
    const DATE = '{{ DATE }}';
    const TIME = '{{ TIME }}';
    const YEAR = '{{ YEAR }}';
    const MONTH = '{{ MONTH }}';
    const DAY = '{{ DAY }}';
    const UNIQUE_ID = '{{ UNIQUE_ID }}';
    const AGENT = '{{ AGENT }}';
    const BRAIN_FILE = '{{ BRAIN_FILE }}';
    const MCP_FILE = '{{ MCP_FILE }}';
    const BRAIN_FOLDER = '{{ BRAIN_FOLDER }}';
    const AGENTS_FOLDER = '{{ AGENTS_FOLDER }}';
    const COMMANDS_FOLDER = '{{ COMMANDS_FOLDER }}';
    const SKILLS_FOLDER = '{{ SKILLS_FOLDER }}';

    public static function print(string $name): string
    {
        return "{{ " . strtoupper($name) . " }}";
    }

    public static function PROJECT_DIRECTORY(...$append): string
    {
        return static::PROJECT_DIRECTORY . (count($append) ? implode('/', $append) : '');
    }

    public static function BRAIN_DIRECTORY(...$append): string
    {
        return static::BRAIN_DIRECTORY . (count($append) ? implode('/', $append) : '');
    }

    public static function NODE_DIRECTORY(...$append): string
    {
        return static::NODE_DIRECTORY . (count($append) ? implode('/', $append) : '');
    }

    public static function BRAIN_FOLDER(...$append): string
    {
        return static::BRAIN_FOLDER . (count($append) ? implode('/', $append) : '');
    }

    public static function AGENTS_FOLDER(...$append): string
    {
        return static::AGENTS_FOLDER . (count($append) ? implode('/', $append) : '');
    }

    public static function COMMANDS_FOLDER(...$append): string
    {
        return static::COMMANDS_FOLDER . (count($append) ? implode('/', $append) : '');
    }

    public static function SKILLS_FOLDER(...$append): string
    {
        return static::SKILLS_FOLDER . (count($append) ? implode('/', $append) : '');
    }

    public static function __callStatic(string $name, array $arguments): string
    {
        $const = static::class . '::' . strtoupper($name);

        if (defined($const)) {
            return constant($const);
        }

        return static::print($name);
    }
}
