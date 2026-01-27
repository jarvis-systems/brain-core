<?php

declare(strict_types=1);

namespace BrainCore\Compilation;

use BrainCore\Compilation\Traits\CompileStandartsTrait;
use BrainCore\MD;

class Operator
{
    use CompileStandartsTrait;

    /**
     * Threshold for inline vs block formatting (characters).
     */
    private const INLINE_THRESHOLD = 60;

    /**
     * Generate IF statement with automatic inline/block formatting.
     *
     * Inline (short): IF(condition) → action
     * Inline with ELSE: IF(condition) → then | ELSE → else
     * Block (long/multi): IF(condition) →\n  action\n→ END-IF
     */
    public static function if(string|array $condition, string|array $then, string|array|null $else = null): string
    {
        $condStr = is_array($condition) ? static::flattenArray($condition) : $condition;
        $thenStr = is_array($then) ? static::flattenArray($then, ' ' . MD::ARROW . ' ') : $then;
        $elseStr = $else ? (is_array($else) ? static::flattenArray($else, ' ' . MD::ARROW . ' ') : $else) : null;

        $thenLines = is_array($then) ? count($then) : 1;
        $elseLines = $else && is_array($else) ? count($else) : ($else ? 1 : 0);

        // Determine if inline or block format
        $isMultiLine = $thenLines > 1 || $elseLines > 1;
        $totalLen = strlen($condStr) + strlen($thenStr) + ($elseStr ? strlen($elseStr) + 10 : 0);
        $useBlock = $isMultiLine || $totalLen > self::INLINE_THRESHOLD;

        if ($useBlock) {
            return static::ifBlock($condStr, $then, $else);
        }

        // Inline format
        $result = 'IF(' . $condStr . ') ' . MD::ARROW . ' ' . $thenStr;
        if ($elseStr) {
            $result .= ' | ELSE ' . MD::ARROW . ' ' . $elseStr;
        }

        return $result;
    }

    /**
     * Generate block-formatted IF statement with END marker.
     */
    public static function ifBlock(string $condition, string|array $then, string|array|null $else = null): string
    {
        $lines = ['IF(' . $condition . ') ' . MD::ARROW];

        // Then block
        $thenItems = is_array($then) ? $then : [$then];
        foreach ($thenItems as $item) {
            $lines[] = '  ' . $item;
        }

        // Else block
        if ($else !== null) {
            $lines[] = MD::ARROW . ' ELSE ' . MD::ARROW;
            $elseItems = is_array($else) ? $else : [$else];
            foreach ($elseItems as $item) {
                $lines[] = '  ' . $item;
            }
        }

        $lines[] = MD::ARROW . ' END-IF';

        return implode("\n", $lines);
    }

    /**
     * Generate FOREACH with consistent block formatting.
     */
    public static function forEach(string|array $condition, string|array $body): string
    {
        $condStr = is_array($condition) ? static::flattenArray($condition) : $condition;
        $bodyItems = is_array($body) ? $body : [$body];

        // Single short item = inline
        if (count($bodyItems) === 1 && strlen($bodyItems[0]) < self::INLINE_THRESHOLD) {
            return 'FOREACH(' . $condStr . ') ' . MD::ARROW . ' ' . $bodyItems[0];
        }

        // Block format
        $lines = ['FOREACH(' . $condStr . ') ' . MD::ARROW];
        foreach ($bodyItems as $item) {
            $lines[] = '  ' . $item;
        }
        $lines[] = MD::ARROW . ' END-FOREACH';

        return implode("\n", $lines);
    }

    /**
     * Generate VALIDATE with FAILS clause.
     */
    public static function validate(string|array $condition, string|array|null $fails = null): string
    {
        $condStr = is_array($condition) ? static::flattenArray($condition, ' && ') : $condition;
        $failsStr = $fails ? (is_array($fails) ? static::flattenArray($fails) : $fails) : null;

        $result = 'VALIDATE(' . $condStr . ')';
        if ($failsStr) {
            $result .= ' ' . MD::ARROW . ' FAILS ' . MD::ARROW . ' ' . $failsStr;
        }

        return $result;
    }

    /**
     * Generate TASK block with consistent formatting.
     */
    public static function task(...$body): string
    {
        if (count($body) === 1 && is_string($body[0]) && strlen($body[0]) < self::INLINE_THRESHOLD) {
            return 'TASK ' . MD::ARROW . ' ' . $body[0];
        }

        $lines = ['TASK ' . MD::ARROW];
        foreach ($body as $item) {
            if (is_array($item)) {
                foreach ($item as $subItem) {
                    $lines[] = '  ' . $subItem;
                }
            } else {
                $lines[] = '  ' . $item;
            }
        }
        $lines[] = MD::ARROW . ' END-TASK';

        return implode("\n", $lines);
    }

    /**
     * Verify success of operation.
     * Format: VERIFY-SUCCESS(args)
     */
    public static function verify(...$args): string
    {
        return 'VERIFY-SUCCESS(' . static::flattenArray($args) . ')';
    }

    /**
     * Check condition.
     * Format: CHECK(args)
     */
    public static function check(...$args): string
    {
        return 'CHECK(' . static::flattenArray($args) . ')';
    }

    /**
     * Define scenario context.
     * Format: SCENARIO(description)
     */
    public static function scenario(...$args): string
    {
        return 'SCENARIO(' . static::flattenArray($args) . ')';
    }

    /**
     * Define goal.
     * Format: GOAL(description)
     */
    public static function goal(...$args): string
    {
        return 'GOAL(' . static::flattenArray($args) . ')';
    }

    /**
     * Report status/result.
     * Format: REPORT(message)
     */
    public static function report(...$args): string
    {
        return 'REPORT(' . static::flattenArray($args) . ')';
    }

    /**
     * Inline action chain with arrows.
     * Format: action1 → action2 → action3
     */
    public static function do(...$args): string
    {
        return implode(' ' . MD::ARROW . ' ', array_filter(array_map(
            fn($arg) => is_array($arg) ? static::flattenArray($arg) : $arg,
            $args
        )));
    }

    /**
     * Alias for do() - chain actions.
     */
    public static function chain(...$args): string
    {
        return static::do(...$args);
    }

    /**
     * Skip with reason.
     * Format: SKIP(reason)
     */
    public static function skip(...$args): string
    {
        return 'SKIP(' . static::flattenArray($args) . ')';
    }

    /**
     * Add note/comment.
     * Format: NOTE(text)
     */
    public static function note(...$args): string
    {
        return 'NOTE(' . static::flattenArray($args) . ')';
    }

    /**
     * Define context.
     * Format: CONTEXT(data)
     */
    public static function context(...$args): string
    {
        return 'CONTEXT(' . static::flattenArray($args) . ')';
    }

    /**
     * Define output format.
     * Format: OUTPUT(format)
     */
    public static function output(...$args): string
    {
        return 'OUTPUT(' . static::flattenArray($args) . ')';
    }

    /**
     * Define input requirements.
     * Format: INPUT(param1 && param2)
     */
    public static function input(...$args): string
    {
        return 'INPUT(' . static::flattenArray($args, ' && ') . ')';
    }

    /**
     * Delegate to agent.
     * Format: [DELEGATE] @agent-name: 'instruction'
     */
    public static function delegate(string $masterId, string $instruction = ''): string
    {
        if (class_exists($masterId) && method_exists($masterId, 'id')) {
            $masterId = $masterId::id();
        }

        $result = '[DELEGATE] ' . $masterId;
        if ($instruction) {
            $result .= ': \'' . $instruction . '\'';
        }

        return $result;
    }

    /**
     * Parallel execution block.
     * Format: [PARALLEL] → (agent1: 'task1' + agent2: 'task2') → END-PARALLEL
     */
    public static function parallel(array $tasks): string
    {
        $taskStrs = [];
        foreach ($tasks as $agent => $task) {
            if (is_int($agent)) {
                $taskStrs[] = $task;
            } else {
                $taskStrs[] = $agent . ': \'' . $task . '\'';
            }
        }

        return '[PARALLEL] ' . MD::ARROW . ' (' . implode(' + ', $taskStrs) . ') ' . MD::ARROW . ' END-PARALLEL';
    }

    /**
     * Abort with message.
     * Format: ABORT "message"
     */
    public static function abort(string $message = ''): string
    {
        return 'ABORT' . ($message ? ' "' . $message . '"' : '');
    }

    /**
     * Return/exit with value.
     * Format: RETURN value
     */
    public static function return(string $value = ''): string
    {
        return 'RETURN' . ($value ? ' ' . $value : '');
    }

    /**
     * Continue to next iteration.
     * Format: CONTINUE
     */
    public static function continue(): string
    {
        return 'CONTINUE';
    }

    /**
     * Break from loop.
     * Format: BREAK
     */
    public static function break(): string
    {
        return 'BREAK';
    }
}
