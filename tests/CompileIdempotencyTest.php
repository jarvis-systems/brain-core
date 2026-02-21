<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Merger;
use BrainCore\XmlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Proof Pack: Compile Idempotency Invariants.
 *
 * Enterprise invariant: same source → Merger → Builder → identical output
 * across multiple runs. This is the foundation of deterministic builds —
 * if the pipeline is non-idempotent, compiled artifacts drift between runs.
 */
class CompileIdempotencyTest extends TestCase
{
    /**
     * Build a realistic Brain-like structure with includes, rules, guidelines.
     * Mirrors the complexity of actual BrainIncludesTrait compilation.
     */
    private function buildRealisticStructure(): array
    {
        return [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'meta',
                    'single' => false,
                    'child' => [
                        ['element' => 'id', 'text' => 'brain-core', 'single' => false, 'child' => []],
                    ],
                ],
                [
                    'element' => 'purpose',
                    'text' => 'Two-package AI agent orchestration system with compile-time architecture.',
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'text' => 'Vector memory iron rules with cookbook delegation.',
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'text' => 'Vector task iron rules with cookbook delegation.',
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
                            'element' => 'rule',
                            'single' => false,
                            'child' => [
                                ['element' => 'id', 'text' => 'mcp-json-only', 'single' => false, 'child' => []],
                                ['element' => 'severity', 'text' => 'critical', 'single' => false, 'child' => []],
                                ['element' => 'text', 'text' => 'ALL memory operations MUST use MCP tool with JSON object payload.', 'single' => false, 'child' => []],
                                ['element' => 'why', 'text' => 'MCP ensures embedding generation and data integrity.', 'single' => false, 'child' => []],
                                ['element' => 'on_violation', 'text' => 'mcp__vector-memory__search_memories({"limit":3,"query":"{insight_summary}"})', 'single' => false, 'child' => []],
                            ],
                        ],
                        [
                            'element' => 'guideline',
                            'single' => false,
                            'child' => [
                                ['element' => 'id', 'text' => 'cookbook-preset', 'single' => false, 'child' => []],
                                ['element' => 'text', 'text' => 'Active cookbook preset for memory operations. Mode: exhaustive/paranoid', 'single' => false, 'child' => []],
                            ],
                        ],
                    ],
                    'includes' => [],
                ],
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'rule',
                            'single' => false,
                            'child' => [
                                ['element' => 'id', 'text' => 'explore-before-execute', 'single' => false, 'child' => []],
                                ['element' => 'severity', 'text' => 'critical', 'single' => false, 'child' => []],
                                ['element' => 'text', 'text' => 'MUST explore task context (parent, children) BEFORE execution.', 'single' => false, 'child' => []],
                            ],
                        ],
                    ],
                    'includes' => [],
                ],
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'guideline',
                            'single' => false,
                            'child' => [
                                ['element' => 'id', 'text' => 'directive', 'single' => false, 'child' => []],
                                ['element' => 'text', 'text' => 'Core directive: "Ultrathink. Delegate. Validate. Reflect."', 'single' => false, 'child' => []],
                            ],
                        ],
                    ],
                    'includes' => [
                        [
                            'element' => 'system',
                            'single' => false,
                            'child' => [
                                [
                                    'element' => 'rule',
                                    'single' => false,
                                    'child' => [
                                        ['element' => 'id', 'text' => 'file-safety', 'single' => false, 'child' => []],
                                        ['element' => 'severity', 'text' => 'critical', 'single' => false, 'child' => []],
                                        ['element' => 'text', 'text' => 'The Brain never edits project files; it only reads them.', 'single' => false, 'child' => []],
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

    /**
     * Invoke protected handle() via Reflection.
     */
    private function merge(array $structure): array
    {
        $merger = new Merger($structure);

        return (new \ReflectionMethod($merger, 'handle'))->invoke($merger);
    }

    /**
     * Full pipeline: Structure → Merger → XmlBuilder must produce
     * identical output on every invocation.
     */
    public function testFullPipelineIdempotency(): void
    {
        $structure = $this->buildRealisticStructure();

        $output1 = XmlBuilder::from($this->merge($structure));
        $output2 = XmlBuilder::from($this->merge($structure));
        $output3 = XmlBuilder::from($this->merge($structure));

        $this->assertSame($output1, $output2, 'Pipeline run 1 vs 2 must be identical');
        $this->assertSame($output2, $output3, 'Pipeline run 2 vs 3 must be identical');
    }

    /**
     * Pipeline must preserve MCP JSON payloads exactly.
     * JSON in text content must survive Merger + Builder without mutation.
     */
    public function testPipelinePreservesMcpJsonPayloads(): void
    {
        $mcpPayload = '{"limit":50,"status":"in_progress"}';

        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'rule',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'on_violation',
                            'text' => 'mcp__vector-task__task_list(' . $mcpPayload . ')',
                            'single' => false,
                            'child' => [],
                        ],
                    ],
                ],
            ],
            'includes' => [],
        ];

        $xml = XmlBuilder::from($this->merge($structure));

        $this->assertStringContainsString(
            $mcpPayload,
            $xml,
            'MCP JSON payload must be preserved through pipeline'
        );
    }

    /**
     * Pipeline with 3-level nested includes must produce deterministic output.
     */
    public function testNestedIncludesProduceDeterministicOutput(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                ['element' => 'purpose', 'text' => 'Root purpose', 'single' => false, 'child' => []],
            ],
            'includes' => [
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'provides', 'text' => 'Level 1 include', 'single' => false, 'child' => []],
                    ],
                    'includes' => [
                        [
                            'element' => 'system',
                            'single' => false,
                            'child' => [
                                ['element' => 'provides', 'text' => 'Level 2 include', 'single' => false, 'child' => []],
                            ],
                            'includes' => [
                                [
                                    'element' => 'system',
                                    'single' => false,
                                    'child' => [
                                        ['element' => 'provides', 'text' => 'Level 3 include', 'single' => false, 'child' => []],
                                    ],
                                    'includes' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $xml1 = XmlBuilder::from($this->merge($structure));
        $xml2 = XmlBuilder::from($this->merge($structure));

        $this->assertSame($xml1, $xml2, '3-level nested includes must produce identical output');

        $this->assertStringContainsString('Level 1 include', $xml1);
        $this->assertStringContainsString('Level 2 include', $xml1);
        $this->assertStringContainsString('Level 3 include', $xml1);
    }

    /**
     * Pipeline must handle special characters deterministically.
     * XML escaping must be consistent across runs.
     */
    public function testSpecialCharacterEscapingIsDeterministic(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'rule',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'text',
                            'text' => 'Token usage < 90% && no "active" compaction',
                            'single' => false,
                            'child' => [],
                        ],
                        [
                            'element' => 'on_violation',
                            'text' => 'Check: $TOKEN_USAGE & retry',
                            'single' => false,
                            'child' => [],
                        ],
                    ],
                ],
            ],
            'includes' => [],
        ];

        $xml1 = XmlBuilder::from($this->merge($structure));
        $xml2 = XmlBuilder::from($this->merge($structure));

        $this->assertSame($xml1, $xml2, 'Special character escaping must be deterministic');
    }
}
