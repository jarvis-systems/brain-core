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
}
