<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\XmlBuilder;
use PHPUnit\Framework\TestCase;

class XmlBuilderTest extends TestCase
{
    public function testBuildsBrainXmlWithoutIndentation(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'sections',
                    'single' => false,
                    'order' => 'strict',
                    'child' => [
                        [
                            'element' => 'section',
                            'name' => 'meta',
                            'brief' => 'Response metadata',
                            'required' => true,
                            'single' => true,
                            'child' => [],
                        ],
                    ],
                ],
                [
                    'element' => 'code_blocks',
                    'policy' => 'Strict formatting; no extraneous comments.',
                    'single' => true,
                    'child' => [],
                ],
            ],
        ];
        $xml = XmlBuilder::from($structure);

        $this->assertStringStartsWith('<system>', $xml);
        $this->assertStringContainsString("\n<section name=\"meta\" brief=\"Response metadata\" required=\"true\"/>", $xml);
        $this->assertStringContainsString("</sections>\n\n<code_blocks", $xml);
        $this->assertStringNotContainsString("\t", $xml, 'XML output must not contain tab characters.');
        $this->assertStringNotContainsString(' </', $xml, 'XML output should not introduce indentation spaces.');
    }

    public function testSelfClosingForSingleNodes(): void
    {
        $structure = [
            'element' => 'root',
            'child' => [
                [
                    'element' => 'leaf',
                    'single' => true,
                    'child' => [],
                ],
            ],
            'single' => false,
        ];

        $xml = XmlBuilder::from($structure);

        $this->assertSame("<root>\n<leaf/>\n</root>", $xml);
    }

    /**
     * Verifies the raw passthrough contract: special characters are NOT escaped.
     * This is intentional — Brain output is a mixed dialect for LLM consumption,
     * not well-formed XML for parsers.
     *
     * @see XmlBuilder::raw()
     * @see .docs/architecture/output-dialect.md
     */
    public function testInlineTextRawPassthrough(): void
    {
        $structure = [
            'element' => 'root',
            'child' => [
                [
                    'element' => 'title',
                    'text' => 'Hello & world',
                    'child' => [],
                    'single' => false,
                ],
            ],
            'single' => false,
        ];

        $xml = XmlBuilder::from($structure);

        // Contract: raw() passthrough — & stays as &, NOT &amp;
        $this->assertSame("<root>\n<title>Hello & world</title>\n</root>", $xml);
    }

    /**
     * Verifies that escapeXml() performs genuine XML escaping
     * for contexts that require well-formed XML.
     */
    public function testEscapeXmlPerformsRealEscaping(): void
    {
        $builder = new class(['element' => 'test']) extends XmlBuilder {
            public function callEscapeXml(string $value): string
            {
                return $this->escapeXml($value);
            }

            public function callRaw(string $value): string
            {
                return $this->raw($value);
            }
        };

        // escapeXml() MUST escape XML special characters
        $this->assertSame('Hello &amp; world', $builder->callEscapeXml('Hello & world'));
        $this->assertSame('a &lt; b &gt; c', $builder->callEscapeXml('a < b > c'));
        $this->assertSame('key=&quot;val&quot;', $builder->callEscapeXml('key="val"'));

        // raw() MUST passthrough unchanged
        $this->assertSame('Hello & world', $builder->callRaw('Hello & world'));
        $this->assertSame('a < b > c', $builder->callRaw('a < b > c'));
    }

    // ──────────────────────────────────────────────
    // Edge cases: empty/null inputs
    // ──────────────────────────────────────────────

    public function testEmptyElementReturnsEmptyString(): void
    {
        $structure = ['element' => '', 'child' => [], 'single' => false];
        $xml = XmlBuilder::from($structure);

        $this->assertSame('', $xml);
    }

    public function testEmptyChildrenArrayRendersCorrectly(): void
    {
        $structure = [
            'element' => 'container',
            'text' => null,
            'child' => [],
            'single' => false,
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertSame("<container>\n</container>", $xml);
    }

    public function testNullTextWithNoChildrenRendersOpenClose(): void
    {
        $structure = [
            'element' => 'empty',
            'text' => null,
            'child' => [],
            'single' => false,
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertSame("<empty>\n</empty>", $xml);
    }

    public function testSingleWithEmptyContentSelfCloses(): void
    {
        $structure = [
            'element' => 'leaf',
            'text' => null,
            'child' => [],
            'single' => true,
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertSame('<leaf/>', $xml);
    }

    public function testSingleWithEmptyStringTextSelfCloses(): void
    {
        $structure = [
            'element' => 'leaf',
            'text' => '',
            'child' => [],
            'single' => true,
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertSame('<leaf/>', $xml);
    }

    // ──────────────────────────────────────────────
    // Edge cases: deep nesting
    // ──────────────────────────────────────────────

    public function testDeepNestingRendersDeterministically(): void
    {
        $structure = [
            'element' => 'level0',
            'single' => false,
            'child' => [
                [
                    'element' => 'level1',
                    'single' => false,
                    'child' => [
                        [
                            'element' => 'level2',
                            'single' => false,
                            'child' => [
                                [
                                    'element' => 'level3',
                                    'text' => 'deep content',
                                    'single' => false,
                                    'child' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $first = XmlBuilder::from($structure);
        $second = XmlBuilder::from($structure);

        $this->assertSame($first, $second, 'Deep nesting must be deterministic');
        $this->assertStringContainsString('<level3>deep content</level3>', $first);
        $this->assertStringContainsString('<level0>', $first);
        $this->assertStringContainsString('</level0>', $first);
    }

    public function testNonArrayChildrenAreSkipped(): void
    {
        $structure = [
            'element' => 'root',
            'single' => false,
            'child' => [
                'not-an-array',
                42,
                null,
                ['element' => 'valid', 'text' => 'ok', 'single' => false, 'child' => []],
            ],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertStringContainsString('<valid>ok</valid>', $xml);
        $this->assertStringNotContainsString('not-an-array', $xml);
    }

    // ──────────────────────────────────────────────
    // Edge cases: attributes
    // ──────────────────────────────────────────────

    public function testBooleanAttributeFormatsTrueFalse(): void
    {
        $structure = [
            'element' => 'feature',
            'enabled' => true,
            'deprecated' => false,
            'single' => true,
            'child' => [],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertStringContainsString('enabled="true"', $xml);
        $this->assertStringContainsString('deprecated="false"', $xml);
    }

    public function testMultipleAttributesOnElement(): void
    {
        $structure = [
            'element' => 'section',
            'name' => 'meta',
            'brief' => 'desc',
            'required' => true,
            'single' => true,
            'child' => [],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertStringContainsString('name="meta"', $xml);
        $this->assertStringContainsString('brief="desc"', $xml);
        $this->assertStringContainsString('required="true"', $xml);
        $this->assertStringContainsString('<section ', $xml);
        $this->assertStringContainsString('/>', $xml);
    }

    public function testNullAttributeIsOmitted(): void
    {
        $structure = [
            'element' => 'item',
            'name' => 'test',
            'optional' => null,
            'single' => true,
            'child' => [],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertStringContainsString('name="test"', $xml);
        $this->assertStringNotContainsString('optional', $xml);
    }

    // ──────────────────────────────────────────────
    // Edge cases: newline/spacing contract
    // ──────────────────────────────────────────────

    public function testNoTabsInOutput(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'purpose',
                    'text' => "Text\twith\ttabs",
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'single' => false,
                    'child' => [
                        ['element' => 'item', 'text' => 'nested', 'single' => false, 'child' => []],
                    ],
                ],
            ],
        ];

        $xml = XmlBuilder::from($structure);
        // Tabs inside text content are passthrough (raw), but no structural tabs
        $this->assertStringNotContainsString("\t<", $xml, 'No tab-indented elements');
        $this->assertStringNotContainsString("\t</", $xml, 'No tab-indented closing tags');
    }

    public function testDoubleNewlineBetweenTopLevelSiblings(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                ['element' => 'alpha', 'text' => 'a', 'single' => false, 'child' => []],
                ['element' => 'beta', 'text' => 'b', 'single' => false, 'child' => []],
                ['element' => 'gamma', 'text' => 'c', 'single' => false, 'child' => []],
            ],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertStringContainsString("</alpha>\n\n<beta>", $xml);
        $this->assertStringContainsString("</beta>\n\n<gamma>", $xml);
    }

    // ──────────────────────────────────────────────
    // Edge cases: cache mechanism
    // ──────────────────────────────────────────────

    public function testFromCacheReturnsSameResult(): void
    {
        $structure = [
            'element' => 'cached',
            'text' => 'value',
            'single' => false,
            'child' => [],
        ];

        $first = XmlBuilder::from($structure);
        $second = XmlBuilder::from($structure);

        $this->assertSame($first, $second, 'Cache must return identical result');
    }

    // ──────────────────────────────────────────────
    // Edge cases: inline text vs block rendering
    // ──────────────────────────────────────────────

    public function testInlineTextRendersOnOneLine(): void
    {
        $structure = [
            'element' => 'tag',
            'text' => 'short value',
            'single' => false,
            'child' => [],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertSame('<tag>short value</tag>', $xml);
    }

    public function testTextWithChildrenRendersAsBlock(): void
    {
        $structure = [
            'element' => 'container',
            'text' => 'parent text',
            'single' => false,
            'child' => [
                ['element' => 'item', 'text' => 'child text', 'single' => false, 'child' => []],
            ],
        ];

        $xml = XmlBuilder::from($structure);
        $this->assertStringContainsString("parent text\n", $xml);
        $this->assertStringContainsString('<item>child text</item>', $xml);
    }
}
