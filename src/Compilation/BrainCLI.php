<?php

declare(strict_types=1);

namespace BrainCore\Compilation;

class BrainCLI
{
    const COMPILE = 'brain compile';
    const HELP = 'brain help';
    const DOCS = 'brain docs';
    const INIT = 'brain init';
    const LIST = 'brain list';
    const UPDATE = 'brain update';
    const MAKE_COMMAND = 'brain make:command';
    const MAKE_INCLUDE = 'brain make:include';
    const MAKE_MASTER = 'brain make:master';
    const MAKE_MCP = 'brain make:mcp';
    const MAKE_SKILL = 'brain make:skill';
    const MAKE_SCRIPT = 'brain make:script';

    const LIST_MASTERS = 'brain list:masters';
    const LIST_INCLUDES = 'brain list:includes';

    private static function isMcpDisabled(): bool
    {
        return getenv('BRAIN_DISABLE_MCP') === 'true'
            || getenv('BRAIN_DISABLE_MCP') === '1';
    }

    public static function MCP__DOCS_SEARCH(array $options = []): string
    {
        if (self::isMcpDisabled()) {
            $keywords = $options['keywords'] ?? '';
            unset($options['keywords']);
            $args = [];
            foreach ($options as $key => $value) {
                if ($value === true) {
                    $args[] = "--$key";
                } elseif ($value !== false && $value !== null) {
                    $args[] = "--$key=" . addslashes((string)$value);
                }
            }
            $args[] = "--json";
            return 'brain docs ' . addslashes($keywords) . ' ' . implode(' ', $args);
        }
        self::ksortRecursive($options);
        try {
            $json = json_encode(
                $options,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            return 'mcp__brain-tools__docs_search(' . $json . ')';
        } catch (\JsonException $e) {
            // Handle JSON encoding error if necessary
            throw new \RuntimeException('Failed to encode options to JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function MCP__DIAGNOSE(array $options = []): string
    {
        if (self::isMcpDisabled()) {
            $args = [];
            foreach ($options as $key => $value) {
                if ($value === true) {
                    $args[] = "--$key";
                } elseif ($value !== false && $value !== null) {
                    $args[] = "--$key=" . addslashes((string)$value);
                }
            }
            return 'brain diagnose' . ($args ? ' ' . implode(' ', $args) : '');
        }
        self::ksortRecursive($options);
        try {
            $json = json_encode(
                $options,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            return 'mcp__brain-tools__diagnose(' . $json . ')';
        } catch (\JsonException $e) {
            // Handle JSON encoding error if necessary
            throw new \RuntimeException('Failed to encode options to JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function MCP__LIST_MASTERS(array $options = []): string
    {
        if (self::isMcpDisabled()) {
            $args = [];
            foreach ($options as $key => $value) {
                if ($value === true) {
                    $args[] = "--$key";
                } elseif ($value !== false && $value !== null) {
                    $args[] = "--$key=" . addslashes((string)$value);
                }
            }
            return 'brain list:masters' . ($args ? ' ' . implode(' ', $args) : '');
        }
        self::ksortRecursive($options);
        try {
            $json = json_encode(
                $options,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            return 'mcp__brain-tools__list-masters(' . $json . ')';
        } catch (\JsonException $e) {
            // Handle JSON encoding error if necessary
            throw new \RuntimeException('Failed to encode options to JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function ksortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
        ksort($array);
    }

    public static function DOCS(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::DOCS
            . (!empty($arguments) ? " $arguments" : '');
    }

    public static function MAKE_COMMAND(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::MAKE_COMMAND
            . (!empty($arguments) ? " $arguments" : '');
    }

    public static function MAKE_INCLUDE(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::MAKE_INCLUDE
            . (!empty($arguments) ? " $arguments" : '');
    }

    public static function MAKE_MASTER(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::MAKE_MASTER
            . (!empty($arguments) ? " $arguments" : '');
    }

    public static function MAKE_MCP(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::MAKE_MCP
            . (!empty($arguments) ? " $arguments" : '');
    }

    public static function MAKE_SKILL(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::MAKE_SKILL
            . (!empty($arguments) ? " $arguments" : '');
    }

    public static function MAKE_SCRIPT(...$args): string
    {
        $arguments = implode(' ', $args);
        return static::MAKE_SCRIPT
            . (!empty($arguments) ? " $arguments" : '');
    }
}
