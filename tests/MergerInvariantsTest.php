<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Merger;
use PHPUnit\Framework\TestCase;

/**
 * Proof Pack: Merger Invariants.
 *
 * Enterprise invariant: Merger must flatten includes deterministically,
 * preserve element order, deduplicate correctly, and never lose data.
 */
class MergerInvariantsTest extends TestCase
{
    /**
     * Invoke protected handle() via Reflection.
     * Standard PHPUnit pattern for testing internals.
     */
    private function merge(array $structure): array
    {
        $merger = new Merger($structure);

        return (new \ReflectionMethod($merger, 'handle'))->invoke($merger);
    }

    /**
     * Merger must not lose children during include flattening.
     * Every child from both the node and its includes must appear in output.
     */
    public function testNoChildLossDuringMerge(): void
    {
        // Includes always compile with element => 'system' (see MergerTest::buildInclude).
        // mergeNodeData() has special path: when incoming.element === 'system'
        // and base.element differs, incoming's children merge into base's children.
        $structure = [
            'element' => 'system',
            'child' => [
                ['element' => 'existing', 'child' => [], 'single' => true],
            ],
            'includes' => [
                [
                    'element' => 'system',
                    'child' => [
                        ['element' => 'from_include', 'child' => [], 'single' => true],
                    ],
                    'includes' => [],
                ],
            ],
        ];

        $result = $this->merge($structure);

        $childElements = array_column($result['child'], 'element');

        $this->assertContains('existing', $childElements, 'Original child must survive merge');
        $this->assertContains('from_include', $childElements, 'Include child must be merged in');
    }

    /**
     * Merger must handle empty includes without breaking.
     */
    public function testEmptyIncludesProduceIdenticalOutput(): void
    {
        $structure = [
            'element' => 'root',
            'child' => [
                ['element' => 'child1', 'child' => [], 'single' => true],
            ],
            'includes' => [],
        ];

        $result = $this->merge($structure);

        $this->assertSame('root', $result['element']);
        $this->assertCount(1, $result['child']);
        $this->assertSame('child1', $result['child'][0]['element']);
        $this->assertArrayNotHasKey('includes', $result, 'includes key must be removed after merge');
    }

    /**
     * Merger must handle deeply nested includes (3 levels).
     * Tests the recursive mergeNode() path.
     */
    public function testDeepNestedIncludesMergeCorrectly(): void
    {
        // Real includes always use element => 'system'.
        // 3-level nesting: root → include(system) → include(system) → deep_child.
        $structure = [
            'element' => 'system',
            'child' => [],
            'includes' => [
                [
                    'element' => 'system',
                    'child' => [],
                    'includes' => [
                        [
                            'element' => 'system',
                            'child' => [
                                ['element' => 'deep_child', 'child' => [], 'single' => true],
                            ],
                            'includes' => [],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->merge($structure);

        $childElements = array_column($result['child'], 'element');

        $this->assertContains('deep_child', $childElements, 'Deep nested include child must be flattened to root');
        $this->assertArrayNotHasKey('includes', $result, 'All includes must be flattened');
    }

    /**
     * Merger must be deterministic: same input → same output.
     */
    public function testMergerIsDeterministic(): void
    {
        $structure = [
            'element' => 'system',
            'child' => [
                ['element' => 'purpose', 'text' => 'Test', 'child' => [], 'single' => false],
            ],
            'includes' => [
                [
                    'element' => 'system',
                    'child' => [
                        ['element' => 'provides', 'text' => 'Feature A', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
            ],
        ];

        // Merger has static cache, so we test with fresh instances
        $merger1 = new Merger($structure);
        $merger2 = new Merger($structure);

        $result1 = (new \ReflectionMethod($merger1, 'handle'))->invoke($merger1);
        $result2 = (new \ReflectionMethod($merger2, 'handle'))->invoke($merger2);

        $this->assertSame(
            json_encode($result1, JSON_THROW_ON_ERROR),
            json_encode($result2, JSON_THROW_ON_ERROR),
            'Merger must produce identical output for identical input'
        );
    }

    // ──────────────────────────────────────────────
    // Contiguous grouping: resolveInsertionIndex
    // ──────────────────────────────────────────────

    /**
     * Contiguous grouping: when two includes contribute children of the
     * same element types, resolveInsertionIndex() must insert them
     * adjacent to existing same-type siblings, not at the end.
     *
     * Covers: resolveInsertionIndex() + array_splice + index rebuild
     * for the case of interleaved element types from multiple includes.
     */
    public function testContiguousGroupingWithInterleavedIncludes(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [],
            'includes' => [
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'provides', 'text' => 'Feature A', 'child' => [], 'single' => false],
                        ['element' => 'guideline', 'id' => 'g1', 'single' => false, 'child' => [
                            ['element' => 'text', 'text' => 'Guideline 1', 'child' => [], 'single' => false],
                        ]],
                        ['element' => 'constraint', 'id' => 'c1', 'text' => 'Constraint 1', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'provides', 'text' => 'Feature B', 'child' => [], 'single' => false],
                        ['element' => 'guideline', 'id' => 'g2', 'single' => false, 'child' => [
                            ['element' => 'text', 'text' => 'Guideline 2', 'child' => [], 'single' => false],
                        ]],
                        ['element' => 'constraint', 'id' => 'c2', 'text' => 'Constraint 2', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
            ],
        ];

        $result = $this->merge($structure);
        $elements = array_column($result['child'], 'element');

        $this->assertSame(
            ['provides', 'provides', 'guideline', 'guideline', 'constraint', 'constraint'],
            $elements,
            'Same-element children from different includes must remain contiguously grouped'
        );
    }

    /**
     * Contiguous grouping with 3 includes and a pre-existing base child.
     *
     * Verifies that resolveInsertionIndex correctly handles the case
     * where base already has children and 3 includes contribute
     * overlapping element types in different order.
     */
    public function testContiguousGroupingThreeIncludes(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                ['element' => 'purpose', 'text' => 'Base purpose', 'child' => [], 'single' => false],
            ],
            'includes' => [
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'provides', 'text' => 'P1', 'child' => [], 'single' => false],
                        ['element' => 'constraint', 'id' => 'x1', 'text' => 'X1', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'purpose', 'text' => 'Extra purpose', 'child' => [], 'single' => false],
                        ['element' => 'provides', 'text' => 'P2', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'constraint', 'id' => 'x2', 'text' => 'X2', 'child' => [], 'single' => false],
                        ['element' => 'provides', 'text' => 'P3', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
            ],
        ];

        $result = $this->merge($structure);
        $elements = array_column($result['child'], 'element');

        // Each group must occupy contiguous indices (max - min + 1 === count)
        $purposeIndices = array_keys(array_filter($elements, fn ($e) => $e === 'purpose'));
        $providesIndices = array_keys(array_filter($elements, fn ($e) => $e === 'provides'));
        $constraintIndices = array_keys(array_filter($elements, fn ($e) => $e === 'constraint'));

        $this->assertCount(2, $purposeIndices);
        $this->assertSame(1, max($purposeIndices) - min($purposeIndices), 'purpose must be contiguous');

        $this->assertCount(3, $providesIndices);
        $this->assertSame(2, max($providesIndices) - min($providesIndices), 'provides must be contiguous');

        $this->assertCount(2, $constraintIndices);
        $this->assertSame(1, max($constraintIndices) - min($constraintIndices), 'constraint must be contiguous');
    }

    /**
     * Edge case: array_splice in mergeChildren shifts all positions
     * after the splice point. buildChildrenIndex must rebuild correctly
     * so subsequent insertions land in the right place.
     *
     * Regression guard for Merger.php lines 189-192:
     * array_splice($current, $insertIndex, 0, [$incomingChild]);
     * $index = $this->buildChildrenIndex($current);
     */
    public function testSpliceIndexRebuildDoesNotCorruptSubsequentInsertions(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                ['element' => 'alpha', 'id' => 'a1', 'text' => 'A1', 'child' => [], 'single' => false],
                ['element' => 'beta', 'id' => 'b1', 'text' => 'B1', 'child' => [], 'single' => false],
                ['element' => 'gamma', 'id' => 'g1', 'text' => 'G1', 'child' => [], 'single' => false],
            ],
            'includes' => [
                [
                    'element' => 'system',
                    'single' => false,
                    'child' => [
                        ['element' => 'alpha', 'id' => 'a2', 'text' => 'A2', 'child' => [], 'single' => false],
                        ['element' => 'beta', 'id' => 'b2', 'text' => 'B2', 'child' => [], 'single' => false],
                        ['element' => 'gamma', 'id' => 'g2', 'text' => 'G2', 'child' => [], 'single' => false],
                    ],
                    'includes' => [],
                ],
            ],
        ];

        $result = $this->merge($structure);
        $elements = array_map(
            fn (array $c): string => $c['element'] . ':' . $c['id'],
            $result['child']
        );

        $this->assertSame(
            ['alpha:a1', 'alpha:a2', 'beta:b1', 'beta:b2', 'gamma:g1', 'gamma:g2'],
            $elements,
            'After splice+rebuild, each new element must insert directly after its same-type sibling'
        );
    }
}
