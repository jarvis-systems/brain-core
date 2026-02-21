<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Merger;
use PHPUnit\Framework\TestCase;

class MergerTest extends TestCase
{
    /**
     * Invoke protected Merger::handle() via Reflection.
     *
     * @param  array<string, mixed>  $structure
     * @return array<string, mixed>
     */
    private function merge(array $structure): array
    {
        $merger = new Merger($structure);

        return (new \ReflectionMethod($merger, 'handle'))->invoke($merger);
    }

    public function testPurposeNodesRemainGrouped(): void
    {
        $structure = [
            'element' => 'system',
            'child' => [],
            'includes' => [
                $this->buildInclude([
                    $this->purposeNode('Coordinates the Brain ecosystem.'),
                    $this->styleNode([
                        $this->styleLeaf('language', 'English'),
                    ]),
                ]),
                $this->buildInclude([
                    $this->purposeNode('Establishes the Brain-level validation framework.'),
                    $this->styleNode([
                        $this->styleLeaf('tone', 'Analytical, methodical, clear, and direct'),
                    ]),
                ]),
            ],
        ];

        $merged = $this->merge($structure);

        $elements = array_map(
            static fn (array $node): string => $node['element'],
            $merged['child']
        );

        $this->assertSame(
            ['purpose', 'purpose', 'style'],
            array_slice($elements, 0, 3),
            'Purpose nodes should stay grouped directly ahead of the aggregated style block.'
        );

        $styleChildren = array_map(
            static fn (array $node): string => $node['element'],
            $merged['child'][2]['child']
        );

        $this->assertSame(
            ['language', 'tone'],
            $styleChildren,
            'Style children should merge and preserve their relative order.'
        );
    }

    public function testResponseContractMergesSectionsByName(): void
    {
        $structure = [
            'element' => 'system',
            'child' => [],
            'includes' => [
                $this->buildInclude([
                    $this->responseContractNode([
                        $this->sectionsNode([
                            $this->sectionNode('meta', 'Response metadata', true),
                            $this->sectionNode('analysis', 'Task analysis', true),
                        ]),
                        $this->codeBlockNode('Strict formatting; no extraneous comments.'),
                    ]),
                ]),
                $this->buildInclude([
                    $this->responseContractNode([
                        $this->sectionsNode([
                            $this->sectionNode('analysis', 'Detailed task analysis', true),
                            $this->sectionNode('delegation', 'Delegation details and agent results', true),
                        ]),
                        $this->codeBlockNode('Cleanly formatted, no inline comments.'),
                    ]),
                ]),
            ],
        ];

        $merged = $this->merge($structure);

        $responseContract = $merged['child'][0];
        $this->assertSame('response_contract', $responseContract['element']);

        $sections = $responseContract['child'][0];
        $this->assertSame('sections', $sections['element']);

        $sectionSummaries = array_map(
            static fn (array $node): array => [
                'name' => $node['name'] ?? null,
                'brief' => $node['brief'] ?? null,
                'required' => $node['required'] ?? null,
            ],
            $sections['child']
        );

        $this->assertSame([
            ['name' => 'meta', 'brief' => 'Response metadata', 'required' => true],
            ['name' => 'analysis', 'brief' => 'Detailed task analysis', 'required' => true],
            ['name' => 'delegation', 'brief' => 'Delegation details and agent results', 'required' => true],
        ], $sectionSummaries);

        $policyTexts = array_values(array_map(
            static fn (array $node): string => $node['policy'] ?? '',
            array_filter(
                $responseContract['child'],
                static fn (array $node): bool => ($node['element'] ?? null) === 'code_blocks'
            )
        ));

        $this->assertSame(
            [
                'Strict formatting; no extraneous comments.',
                'Cleanly formatted, no inline comments.',
            ],
            $policyTexts,
            'Code block policies should append to preserve the declaration order.'
        );
    }

    public function testNestedIncludesAreMergedRecursively(): void
    {
        $structure = [
            'element' => 'system',
            'child' => [
                $this->styleNode([
                    $this->styleLeaf('language', 'English'),
                ]),
            ],
            'includes' => [
                $this->buildInclude([
                    $this->styleNode([
                        $this->styleLeaf('tone', 'Analytical'),
                    ], [
                        $this->buildInclude([
                            $this->styleLeaf('brevity', 'Medium')
                        ]),
                    ]),
                ]),
            ],
            'single' => false,
        ];

        $merged = $this->merge($structure);

        $style = $merged['child'][0];

        $this->assertSame('style', $style['element']);
        $this->assertSame(
            ['language', 'tone', 'brevity'],
            array_map(
                static fn (array $node): string => $node['element'],
                $style['child']
            ),
            'Nested includes should fold into the parent style node in order.'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function buildInclude(array $children): array
    {
        return [
            'element' => 'system',
            'child' => $children,
            'includes' => [],
        ];
    }

    /**
     * @param  non-empty-string  $text
     * @return array<string, mixed>
     */
    private function purposeNode(string $text): array
    {
        return [
            'element' => 'purpose',
            'text' => $text,
            'child' => [],
            'single' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function styleNode(array $children, array $includes = []): array
    {
        return [
            'element' => 'style',
            'text' => null,
            'child' => $children,
            'includes' => $includes,
            'single' => false,
        ];
    }

    /**
     * @param  non-empty-string  $element
     * @param  non-empty-string  $text
     * @return array<string, mixed>
     */
    private function styleLeaf(string $element, string $text): array
    {
        return [
            'element' => $element,
            'text' => $text,
            'child' => [],
            'single' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function responseContractNode(array $children): array
    {
        return [
            'element' => 'response_contract',
            'text' => null,
            'child' => $children,
            'single' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function sectionsNode(array $children): array
    {
        return [
            'element' => 'sections',
            'text' => null,
            'child' => $children,
            'single' => false,
        ];
    }

    /**
     * @param  non-empty-string  $name
     * @param  non-empty-string  $brief
     * @param  bool  $required
     * @return array<string, mixed>
     */
    private function sectionNode(string $name, string $brief, bool $required): array
    {
        return [
            'element' => 'section',
            'name' => $name,
            'brief' => $brief,
            'required' => $required,
            'text' => null,
            'child' => [],
            'single' => true,
        ];
    }

    /**
     * @param  non-empty-string  $policy
     * @return array<string, mixed>
     */
    private function codeBlockNode(string $policy): array
    {
        return [
            'element' => 'code_blocks',
            'policy' => $policy,
            'text' => null,
            'child' => [],
            'single' => true,
        ];
    }
}
