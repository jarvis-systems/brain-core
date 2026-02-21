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
}
