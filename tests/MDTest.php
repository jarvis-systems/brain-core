<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\MD;
use PHPUnit\Framework\TestCase;

/**
 * MD markdown formatting helper contracts.
 *
 * Enterprise invariant: MD is the sole markdown generation layer
 * for prompt compilation. All output must be deterministic, stable,
 * and compatible with LLM prompt parsing expectations.
 */
class MDTest extends TestCase
{
    // ──────────────────────────────────────────────
    // Inline formatting
    // ──────────────────────────────────────────────

    public function testBoldWrapsInDoubleAsterisks(): void
    {
        $this->assertSame('**important**', MD::bold('important'));
        $this->assertSame('****', MD::bold(''));
    }

    public function testCodeWrapsInBackticks(): void
    {
        $this->assertSame('`pending`', MD::code('pending'));
        $this->assertSame('``', MD::code(''));
    }

    public function testCapsUppercasesText(): void
    {
        $this->assertSame('CRITICAL', MD::caps('critical'));
        $this->assertSame('CRITICAL', MD::caps('Critical'));
        $this->assertSame('ALREADY', MD::caps('ALREADY'));
    }

    public function testCriticalCombinesBoldAndCaps(): void
    {
        $this->assertSame('**STOP**', MD::critical('stop'));
        $this->assertSame('**NEVER DO THIS**', MD::critical('never do this'));
    }

    // ──────────────────────────────────────────────
    // Separators and constants
    // ──────────────────────────────────────────────

    public function testSeparatorsReturnExpectedConstants(): void
    {
        $this->assertSame('---', MD::separator());
        $this->assertSame('===', MD::exampleSeparator());
        $this->assertSame('---', MD::SECTION_SEPARATOR);
        $this->assertSame('===', MD::EXAMPLE_SEPARATOR);
        $this->assertSame('→', MD::ARROW);
        $this->assertSame('|', MD::ALT);
    }

    // ──────────────────────────────────────────────
    // Flow and conditional helpers
    // ──────────────────────────────────────────────

    public function testFlowBehavior(): void
    {
        // No args — bare arrow
        $this->assertSame('→', MD::flow());

        // With from and to
        $this->assertSame('input → output', MD::flow('input', 'output'));

        // Only from (to is null) — still bare arrow
        $this->assertSame('→', MD::flow('input'));

        // Only to (from is null) — still bare arrow
        $this->assertSame('→', MD::flow(null, 'output'));
    }

    public function testWhenFormatsConditionArrowResult(): void
    {
        $this->assertSame('error → retry', MD::when('error', 'retry'));
    }

    public function testIfThenFormatsConditional(): void
    {
        // Without else
        $this->assertSame(
            'IF(debug) → log it',
            MD::ifThen('debug', 'log it')
        );

        // With else
        $this->assertSame(
            'IF(debug) → log it | ELSE → skip',
            MD::ifThen('debug', 'log it', 'skip')
        );
    }

    public function testAltJoinsWithPipeSeparator(): void
    {
        $this->assertSame('a | b | c', MD::alt('a', 'b', 'c'));
        $this->assertSame('only', MD::alt('only'));
    }

    // ──────────────────────────────────────────────
    // List builders
    // ──────────────────────────────────────────────

    public function testBulletFormatsWithIndent(): void
    {
        $this->assertSame('- item', MD::bullet('item'));
        $this->assertSame('  - nested', MD::bullet('nested', 1));
        $this->assertSame('    - deep', MD::bullet('deep', 2));
    }

    public function testNumberedFormatsWithIndent(): void
    {
        $this->assertSame('1. first', MD::numbered(1, 'first'));
        $this->assertSame('  3. third', MD::numbered(3, 'third', 1));
    }

    public function testStepWithAndWithoutSubsteps(): void
    {
        // Without substeps — bold action, numbered
        $this->assertSame('1. **Analyze**', MD::step(1, 'Analyze'));

        // With substeps — adds bullet list
        $result = MD::step(2, 'Execute', ['sub-a', 'sub-b']);
        $this->assertStringContainsString('2. **Execute**', $result);
        $this->assertStringContainsString('  - sub-a', $result);
        $this->assertStringContainsString('  - sub-b', $result);

        // Verify substep ordering is stable
        $lines = explode(PHP_EOL, $result);
        $this->assertCount(3, $lines);
        $this->assertSame('2. **Execute**', $lines[0]);
        $this->assertSame('  - sub-a', $lines[1]);
        $this->assertSame('  - sub-b', $lines[2]);
    }

    // ──────────────────────────────────────────────
    // Headers
    // ──────────────────────────────────────────────

    public function testHeaderLevelsAndClamping(): void
    {
        $this->assertSame('# Title', MD::header('Title', 1));
        $this->assertSame('## Sub', MD::header('Sub', 2));
        $this->assertSame('###### Deep', MD::header('Deep', 6));

        // Clamps below 1 → 1
        $this->assertSame('# Clamped', MD::header('Clamped', 0));
        $this->assertSame('# Negative', MD::header('Negative', -5));

        // Clamps above 6 → 6
        $this->assertSame('###### Max', MD::header('Max', 7));
        $this->assertSame('###### Over', MD::header('Over', 100));

        // Default level is 1
        $this->assertSame('# Default', MD::header('Default'));
    }

    // ──────────────────────────────────────────────
    // Structural helpers
    // ──────────────────────────────────────────────

    public function testKvBoldsKeyWithColon(): void
    {
        $this->assertSame('**Status**: running', MD::kv('Status', 'running'));
    }

    public function testDefineFormatsBoldTermDashDefinition(): void
    {
        $this->assertSame('**DTO** - Data Transfer Object', MD::define('DTO', 'Data Transfer Object'));
    }

    public function testSeverityBadge(): void
    {
        $this->assertSame('(CRITICAL)', MD::severity('critical'));
        $this->assertSame('(HIGH)', MD::severity('High'));
        $this->assertSame('(LOW)', MD::severity('LOW'));
    }

    public function testFileRefWithAndWithoutLine(): void
    {
        $this->assertSame('`src/Core.php`', MD::fileRef('src/Core.php'));
        $this->assertSame('`src/Core.php`:42', MD::fileRef('src/Core.php', 42));
    }

    // ──────────────────────────────────────────────
    // Table helpers
    // ──────────────────────────────────────────────

    public function testTableRowAndSeparator(): void
    {
        $this->assertSame('| a | b | c |', MD::tableRow('a', 'b', 'c'));
        $this->assertSame('|---|---|', MD::tableSeparator(2));
        $this->assertSame('|---|---|---|', MD::tableSeparator(3));
    }

    // ──────────────────────────────────────────────
    // Validation result
    // ──────────────────────────────────────────────

    public function testValidationPassAndFail(): void
    {
        $this->assertSame('**✓ PASS**', MD::validation(true));
        $this->assertSame('**✗ FAIL**', MD::validation(false));
        $this->assertSame('**✓ PASS**: all green', MD::validation(true, 'all green'));
        $this->assertSame('**✗ FAIL**: 3 errors', MD::validation(false, '3 errors'));
    }

    // ──────────────────────────────────────────────
    // autoCode — status value detection
    // ──────────────────────────────────────────────

    public function testAutoCodeWrapsKnownStatusValues(): void
    {
        $this->assertSame(
            'Status is `pending`',
            MD::autoCode('Status is pending')
        );

        $this->assertSame(
            'Set to `active` or `inactive`',
            MD::autoCode('Set to active or inactive')
        );

        // All status values must be wrappable
        $statuses = ['pending', 'in_progress', 'completed', 'tested', 'validated',
            'stopped', 'canceled', 'draft', 'active', 'inactive', 'archived',
            'success', 'failure'];

        foreach ($statuses as $status) {
            $this->assertStringContainsString(
                "`{$status}`",
                MD::autoCode("word {$status} word"),
                "autoCode must wrap '{$status}' in backticks"
            );
        }
    }

    public function testAutoCodeSkipsAlreadyBackticked(): void
    {
        // Already in backticks — don't double-wrap
        $this->assertSame(
            'Status: `pending`',
            MD::autoCode('Status: `pending`')
        );
    }

    public function testAutoCodeIsCaseSensitive(): void
    {
        // Uppercase/mixed case should NOT match
        $this->assertSame(
            'Status is PENDING',
            MD::autoCode('Status is PENDING')
        );

        $this->assertSame(
            'Status is Pending',
            MD::autoCode('Status is Pending')
        );
    }

    public function testAutoCodeSkipsQuotedValues(): void
    {
        // Inside quotes — don't wrap
        $this->assertSame(
            'Status: "pending"',
            MD::autoCode('Status: "pending"')
        );
    }

    // ──────────────────────────────────────────────
    // mcpTool formatting
    // ──────────────────────────────────────────────

    public function testMcpToolFormatting(): void
    {
        // Without args
        $this->assertSame(
            '`mcp__vector-memory__store_memory`',
            MD::mcpTool('vector-memory', 'store_memory')
        );

        // With args
        $this->assertSame(
            '`mcp__vector-memory__search_memories`({"query":"test"})',
            MD::mcpTool('vector-memory', 'search_memories', '{"query":"test"}')
        );
    }

    // ──────────────────────────────────────────────
    // fromArray — recursive markdown builder
    // ──────────────────────────────────────────────

    public function testFromArrayFlatScalars(): void
    {
        // Top-level int keys → plain lines (no bullet prefix)
        $result = MD::fromArray([0 => 'one', 1 => 'two']);
        $this->assertSame("one\ntwo", $result);
    }

    public function testFromArrayWithStringKeys(): void
    {
        $result = MD::fromArray(['title' => 'Hello']);
        $this->assertSame("# Title\nHello", $result);
    }

    public function testFromArrayNestedStructure(): void
    {
        $result = MD::fromArray([
            'rules' => [
                'rule-1' => 'No debug artifacts',
                'rule-2' => 'No secrets in code',
            ],
        ]);

        $this->assertStringContainsString('# Rules', $result);
        $this->assertStringContainsString('- rule-1: No debug artifacts', $result);
        $this->assertStringContainsString('- rule-2: No secrets in code', $result);
    }

    public function testFromArrayEmptyReturnsEmpty(): void
    {
        $this->assertSame('', MD::fromArray([]));
    }

    public function testFromArrayStartOffsetControlsHeaderLevel(): void
    {
        $result = MD::fromArray(['section' => 'Content'], 2);
        $this->assertStringStartsWith('### Section', $result);
    }

    // ──────────────────────────────────────────────
    // Determinism proof
    // ──────────────────────────────────────────────

    public function testOutputDeterminism(): void
    {
        $input = [
            'config' => [
                'mode' => 'strict',
                'level' => 'high',
            ],
        ];

        $first = MD::fromArray($input);
        $second = MD::fromArray($input);
        $this->assertSame($first, $second, 'fromArray must be deterministic');

        $this->assertSame(MD::autoCode('status pending'), MD::autoCode('status pending'));
        $this->assertSame(MD::step(1, 'Act', ['a']), MD::step(1, 'Act', ['a']));
    }
}
