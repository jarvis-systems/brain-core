<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Merger;
use BrainCore\XmlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Golden-file snapshot regression tests.
 *
 * Enterprise invariant: compiled output format must not change
 * without deliberate intent. Any rendering change breaks
 * downstream consumers (LLM prompts, agent configurations).
 *
 * If a test fails after an intentional rendering change:
 * 1. Verify the new output is correct
 * 2. Manually replace the fixture file with the new correct output
 * 3. Commit updated fixture with the rendering change
 */
class SnapshotTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    /**
     * Build the standard golden-file input structure.
     *
     * Covers: meta, purpose, provides, iron_rules (2 rules with severity),
     * guidelines (with examples/phases), nested includes (2 levels).
     */
    private function buildStandardStructure(): array
    {
        return [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'meta',
                    'single' => false,
                    'child' => [
                        ['element' => 'id', 'text' => 'test-brain', 'single' => false, 'child' => []],
                    ],
                ],
                [
                    'element' => 'purpose',
                    'text' => 'Test purpose for golden-file snapshot.',
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'text' => 'Core capabilities for testing.',
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'text' => 'Memory management iron rules.',
                    'single' => false,
                    'child' => [],
                ],
            ],
            'includes' => [
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'iron_rules',
                            'single' => false,
                            'child' => [
                                [
                                    'element' => 'rule',
                                    'id' => 'no-secrets',
                                    'severity' => 'critical',
                                    'single' => false,
                                    'child' => [
                                        ['element' => 'text', 'text' => 'NEVER hardcode secrets in source files.', 'single' => false, 'child' => []],
                                        ['element' => 'why', 'text' => 'Secrets in source code leak to version control.', 'single' => false, 'child' => []],
                                        ['element' => 'on_violation', 'text' => 'Remove immediately and rotate credentials.', 'single' => false, 'child' => []],
                                    ],
                                ],
                                [
                                    'element' => 'rule',
                                    'id' => 'determinism',
                                    'severity' => 'high',
                                    'single' => false,
                                    'child' => [
                                        ['element' => 'text', 'text' => 'All builds must be deterministic.', 'single' => false, 'child' => []],
                                        ['element' => 'why', 'text' => 'Non-determinism causes drift between environments.', 'single' => false, 'child' => []],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'element' => 'guidelines',
                            'single' => false,
                            'child' => [
                                [
                                    'element' => 'guideline',
                                    'id' => 'test-first',
                                    'single' => false,
                                    'child' => [
                                        ['element' => 'text', 'text' => 'Write tests before implementation.', 'single' => false, 'child' => []],
                                        [
                                            'element' => 'example',
                                            'text' => 'PHPUnit test case',
                                            'single' => false,
                                            'child' => [
                                                ['element' => 'phase', 'name' => 'step-1', 'text' => 'Create test file', 'single' => false, 'child' => []],
                                                ['element' => 'phase', 'name' => 'step-2', 'text' => 'Run and see it fail', 'single' => false, 'child' => []],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'includes' => [
                        [
                            'element' => 'system',
                            'single' => false,
                            'child' => [
                                [
                                    'element' => 'guideline',
                                    'id' => 'scan-first',
                                    'single' => false,
                                    'child' => [
                                        ['element' => 'text', 'text' => 'Always scan source files before generating code.', 'single' => false, 'child' => []],
                                    ],
                                ],
                            ],
                            'includes' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function merge(array $structure): array
    {
        $merger = new Merger($structure);

        return (new \ReflectionMethod($merger, 'handle'))->invoke($merger);
    }

    // ──────────────────────────────────────────────
    // Full snapshot: standard output
    // ──────────────────────────────────────────────

    public function testStandardOutputMatchesGoldenFile(): void
    {
        $structure = $this->buildStandardStructure();
        $actual = XmlBuilder::from($this->merge($structure));

        $goldenPath = self::FIXTURES_DIR . '/golden-standard.txt';
        $this->assertFileExists($goldenPath, 'Golden file missing: copy actual output to ' . $goldenPath);

        $expected = file_get_contents($goldenPath);
        $this->assertSame(
            $expected,
            $actual,
            "Output differs from golden file.\n"
            . "If this is an intentional change, update: tests/fixtures/golden-standard.txt\n"
            . "Diff hint: compare actual output with the fixture file."
        );
    }

    // ──────────────────────────────────────────────
    // Structural invariants (resilient to formatting)
    // ──────────────────────────────────────────────

    public function testOutputStartsWithSystemTag(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));
        $this->assertStringStartsWith('<system>', $actual);
        $this->assertStringEndsWith('</system>', $actual);
    }

    public function testMetaSectionPresent(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));
        $this->assertStringContainsString('<meta>', $actual);
        $this->assertStringContainsString('<id>test-brain</id>', $actual);
        $this->assertStringContainsString('</meta>', $actual);
    }

    public function testPurposeRenderedInline(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));
        $this->assertStringContainsString(
            '<purpose>Test purpose for golden-file snapshot.</purpose>',
            $actual
        );
    }

    public function testProvidesBlocksPresent(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));
        $this->assertStringContainsString('<provides>Core capabilities for testing.</provides>', $actual);
        $this->assertStringContainsString('<provides>Memory management iron rules.</provides>', $actual);
    }

    public function testIronRulesRenderedAsMarkdown(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));

        // Iron rules header
        $this->assertStringContainsString('# Iron Rules', $actual);

        // Rule formatting with severity badge
        $this->assertStringContainsString('## No-secrets (CRITICAL)', $actual);
        $this->assertStringContainsString('## Determinism (HIGH)', $actual);

        // Rule content
        $this->assertStringContainsString('NEVER hardcode secrets in source files.', $actual);
        $this->assertStringContainsString('**why**', $actual);
        $this->assertStringContainsString('**on_violation**', $actual);
    }

    public function testGuidelineRenderedAsMarkdown(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));

        // Guideline header
        $this->assertStringContainsString('# Test first', $actual);
        $this->assertStringContainsString('Write tests before implementation.', $actual);

        // Example with phases
        $this->assertStringContainsString('PHPUnit test case', $actual);
        $this->assertStringContainsString('`step-1`', $actual);
        $this->assertStringContainsString('`step-2`', $actual);
    }

    public function testNestedIncludesMergedCorrectly(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));

        // Level-2 include content must be present
        $this->assertStringContainsString('# Scan first', $actual);
        $this->assertStringContainsString('Always scan source files before generating code.', $actual);
    }

    public function testNoIncludesKeyInOutput(): void
    {
        $merged = $this->merge($this->buildStandardStructure());

        // After merge, 'includes' key must not exist
        $this->assertArrayNotHasKey('includes', $merged);
    }

    // ──────────────────────────────────────────────
    // Section-hash stability (line-count + hash)
    // ──────────────────────────────────────────────

    public function testOutputLineCountIsStable(): void
    {
        $actual = XmlBuilder::from($this->merge($this->buildStandardStructure()));
        $lines = explode("\n", $actual);

        $goldenPath = self::FIXTURES_DIR . '/golden-standard.txt';
        $expected = file_get_contents($goldenPath);
        $expectedLines = explode("\n", $expected);

        $this->assertCount(
            count($expectedLines),
            $lines,
            'Output line count changed: expected ' . count($expectedLines) . ', got ' . count($lines)
        );
    }

    public function testOutputHashIsStable(): void
    {
        $first = XmlBuilder::from($this->merge($this->buildStandardStructure()));
        $second = XmlBuilder::from($this->merge($this->buildStandardStructure()));

        $this->assertSame(
            md5($first),
            md5($second),
            'Output hash must be stable across runs'
        );
    }

    // ──────────────────────────────────────────────
    // Rule deduplication snapshot
    // ──────────────────────────────────────────────

    public function testDuplicateRuleIdsAreDeduplicatedInOutput(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'iron_rules',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'rule',
                            'id' => 'same-id',
                            'severity' => 'critical',
                            'single' => false,
                            'child' => [
                                ['element' => 'text', 'text' => 'First rule.', 'single' => false, 'child' => []],
                            ],
                        ],
                        [
                            'element' => 'rule',
                            'id' => 'same-id',
                            'severity' => 'high',
                            'single' => false,
                            'child' => [
                                ['element' => 'text', 'text' => 'Duplicate rule — must be skipped.', 'single' => false, 'child' => []],
                            ],
                        ],
                    ],
                ],
            ],
            'includes' => [],
        ];

        $xml = XmlBuilder::from($this->merge($structure));

        // First rule present
        $this->assertStringContainsString('First rule.', $xml);

        // Duplicate must be skipped
        $this->assertStringNotContainsString('Duplicate rule', $xml);
    }
}
