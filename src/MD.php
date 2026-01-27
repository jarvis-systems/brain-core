<?php

declare(strict_types=1);

namespace BrainCore;

/**
 * Markdown formatting helper for prompt compilation.
 *
 * Implements prompt engineering best practices:
 * - Bold for important terms (increases model attention)
 * - Backticks for exact values (prevents semantic confusion)
 * - Separators for section boundaries (reduces context leakage)
 * - Arrows for flow indication (clarifies causality)
 */
class MD
{
    /**
     * Section separator - use between major guideline blocks.
     * Research shows separators reduce "leakage" between sections.
     */
    public const SECTION_SEPARATOR = '---';

    /**
     * Example separator - use between multiple examples.
     */
    public const EXAMPLE_SEPARATOR = '===';

    /**
     * Flow arrow - indicates causality or transformation.
     */
    public const ARROW = '→';

    /**
     * Alternative separator - for listing alternatives inline.
     */
    public const ALT = '|';

    public static function fromArray(array $data, int $start = 0): string
    {
        $md = '';
        $iterationHeaders = $start;
        foreach ($data as $key => $value) {
            if (! is_int($key)) {
                if ($md !== '') {
                    $md .= PHP_EOL;
                }
                $header = str_repeat('#', $iterationHeaders + 1);
                $md .= $header . " " . ucfirst($key) . PHP_EOL;
                $iterationHeaders++;
            }
            if ($value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $subValue = is_array($subValue)
                            ? static::fromArray($subValue, $iterationHeaders)
                            : $subValue;
                        if (is_int($subKey)) {
                            $md .= "- $subValue" . PHP_EOL;
                        } else {
                            $md .= "- $subKey: $subValue" . PHP_EOL;
                        }
                    }
                } else {
                    $md .= $value . PHP_EOL;
                }
            }
        }
        return trim($md);
    }

    /**
     * Bold text for important terms.
     * Use sparingly - bold increases model attention weight.
     *
     * @param string $text Text to bold
     * @return string Markdown bold formatted text
     */
    public static function bold(string $text): string
    {
        return "**{$text}**";
    }

    /**
     * Inline code for exact values/outputs.
     * Signals to model this is a literal value, not semantic content.
     *
     * @param string $text Text to format as code
     * @return string Markdown inline code formatted text
     */
    public static function code(string $text): string
    {
        return "`{$text}`";
    }

    /**
     * All caps for critical emphasis.
     * Use VERY sparingly (1-2 per prompt max).
     *
     * @param string $text Text to capitalize
     * @return string Uppercased text
     */
    public static function caps(string $text): string
    {
        return strtoupper($text);
    }

    /**
     * Bold + caps for maximum emphasis.
     * Use only for truly critical directives.
     *
     * @param string $text Text to emphasize
     * @return string Bold uppercased text
     */
    public static function critical(string $text): string
    {
        return static::bold(static::caps($text));
    }

    /**
     * Section separator.
     * Use between major guideline sections to prevent context leakage.
     *
     * @return string Horizontal rule
     */
    public static function separator(): string
    {
        return self::SECTION_SEPARATOR;
    }

    /**
     * Example separator.
     * Use between multiple examples to clearly delineate them.
     *
     * @return string Triple equals separator
     */
    public static function exampleSeparator(): string
    {
        return self::EXAMPLE_SEPARATOR;
    }

    /**
     * Flow arrow for showing causality or transformation.
     *
     * @param string|null $from Optional source
     * @param string|null $to Optional destination
     * @return string Arrow or flow expression
     */
    public static function flow(?string $from = null, ?string $to = null): string
    {
        if ($from !== null && $to !== null) {
            return "{$from} " . self::ARROW . " {$to}";
        }
        return self::ARROW;
    }

    /**
     * Alternative options inline.
     * Use for listing mutually exclusive options.
     *
     * @param string ...$options Options to join
     * @return string Pipe-separated options
     */
    public static function alt(string ...$options): string
    {
        return implode(' ' . self::ALT . ' ', $options);
    }

    /**
     * Key-value pair with bold key.
     * Standard format for labeled data.
     *
     * @param string $key Label (will be bolded)
     * @param string $value Value
     * @return string Formatted key-value pair
     */
    public static function kv(string $key, string $value): string
    {
        return static::bold($key) . ": {$value}";
    }

    /**
     * Bullet list item.
     *
     * @param string $text Item text
     * @param int $indent Indentation level (0 = top level)
     * @return string Formatted list item
     */
    public static function bullet(string $text, int $indent = 0): string
    {
        $prefix = str_repeat('  ', $indent);
        return "{$prefix}- {$text}";
    }

    /**
     * Numbered list item.
     *
     * @param int $number Item number
     * @param string $text Item text
     * @param int $indent Indentation level
     * @return string Formatted numbered item
     */
    public static function numbered(int $number, string $text, int $indent = 0): string
    {
        $prefix = str_repeat('  ', $indent);
        return "{$prefix}{$number}. {$text}";
    }

    /**
     * Header at specified level.
     *
     * @param string $text Header text
     * @param int $level Header level (1-6)
     * @return string Markdown header
     */
    public static function header(string $text, int $level = 1): string
    {
        $level = max(1, min(6, $level));
        $prefix = str_repeat('#', $level);
        return "{$prefix} {$text}";
    }

    /**
     * Common status/enum values that should be formatted as code.
     * These are literal values the model should output exactly.
     * Only lowercase values to avoid matching words like "CRITICAL" in prose.
     */
    private const STATUS_VALUES = [
        'pending', 'in_progress', 'completed', 'tested', 'validated', 'stopped', 'canceled',
        'draft', 'active', 'inactive', 'archived',
        'success', 'failure',
    ];

    /**
     * Auto-format text with inline code for known status values.
     * Detects status/enum values and wraps them in backticks.
     * Only matches exact lowercase values, not inside quotes or already in backticks.
     *
     * @param string $text Text potentially containing status values
     * @return string Text with status values wrapped in backticks
     */
    public static function autoCode(string $text): string
    {
        foreach (self::STATUS_VALUES as $status) {
            // Match word boundaries, case-sensitive (lowercase only)
            // Don't match if already in backticks or inside quotes
            // Negative lookbehind: not preceded by ` or "
            // Negative lookahead: not followed by ` or "
            $pattern = '/(?<![`"\'])\\b(' . preg_quote($status, '/') . ')\\b(?![`"\'])/';
            $text = preg_replace($pattern, '`$1`', $text);
        }
        return $text;
    }

    /**
     * Format MCP tool call reference.
     * Makes tool calls visually distinct.
     *
     * @param string $server MCP server name
     * @param string $tool Tool name
     * @param string|null $args Optional arguments hint
     * @return string Formatted tool reference
     */
    public static function mcpTool(string $server, string $tool, ?string $args = null): string
    {
        $ref = static::code("mcp__{$server}__{$tool}");
        if ($args !== null) {
            $ref .= "({$args})";
        }
        return $ref;
    }

    /**
     * Format a condition with arrow result.
     * Pattern: condition → result
     *
     * @param string $condition The condition
     * @param string $result The result when condition is true
     * @return string Formatted condition-result pair
     */
    public static function when(string $condition, string $result): string
    {
        return "{$condition} " . self::ARROW . " {$result}";
    }

    /**
     * Format IF-THEN inline pattern.
     * Cleaner than full IF blocks for simple conditions.
     *
     * @param string $condition The condition
     * @param string $then Action when true
     * @param string|null $else Optional action when false
     * @return string Formatted conditional
     */
    public static function ifThen(string $condition, string $then, ?string $else = null): string
    {
        $result = "IF({$condition}) " . self::ARROW . " {$then}";
        if ($else !== null) {
            $result .= " | ELSE " . self::ARROW . " {$else}";
        }
        return $result;
    }

    /**
     * Format a step in a workflow.
     * Numbered step with optional substeps.
     *
     * @param int $number Step number
     * @param string $action Main action description
     * @param array $substeps Optional substeps
     * @return string Formatted step
     */
    public static function step(int $number, string $action, array $substeps = []): string
    {
        $result = static::numbered($number, static::bold($action));
        foreach ($substeps as $substep) {
            $result .= PHP_EOL . static::bullet($substep, 1);
        }
        return $result;
    }

    /**
     * Format a definition/term.
     * Bold term followed by explanation.
     *
     * @param string $term The term being defined
     * @param string $definition The definition/explanation
     * @return string Formatted definition
     */
    public static function define(string $term, string $definition): string
    {
        return static::bold($term) . " - {$definition}";
    }

    /**
     * Format severity badge.
     * Visual indicator for rule/issue severity.
     *
     * @param string $severity Severity level (CRITICAL, HIGH, MEDIUM, LOW)
     * @return string Formatted severity badge
     */
    public static function severity(string $severity): string
    {
        return '(' . strtoupper($severity) . ')';
    }

    /**
     * Format a file:line reference.
     * Standard format for code location references.
     *
     * @param string $file File path
     * @param int|null $line Optional line number
     * @return string Formatted file reference
     */
    public static function fileRef(string $file, ?int $line = null): string
    {
        $ref = static::code($file);
        if ($line !== null) {
            $ref .= ":{$line}";
        }
        return $ref;
    }

    /**
     * Create a simple table row with pipes.
     * Use for inline data comparison.
     *
     * @param string ...$cells Cell values
     * @return string Pipe-separated row
     */
    public static function tableRow(string ...$cells): string
    {
        return '| ' . implode(' | ', $cells) . ' |';
    }

    /**
     * Create table header separator.
     *
     * @param int $columns Number of columns
     * @return string Table header separator row
     */
    public static function tableSeparator(int $columns): string
    {
        return '|' . str_repeat('---|', $columns);
    }

    /**
     * Format a validation result.
     * Shows pass/fail with optional reason.
     *
     * @param bool $passed Whether validation passed
     * @param string|null $reason Optional reason/details
     * @return string Formatted validation result
     */
    public static function validation(bool $passed, ?string $reason = null): string
    {
        $status = $passed ? '✓ PASS' : '✗ FAIL';
        $result = static::bold($status);
        if ($reason !== null) {
            $result .= ": {$reason}";
        }
        return $result;
    }
}
