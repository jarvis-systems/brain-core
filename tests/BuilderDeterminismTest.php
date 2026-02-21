<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\TomlBuilder;
use BrainCore\XmlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Proof Pack: Builder Determinism Invariants.
 *
 * Enterprise invariant: same input → same output, always.
 * This is the foundation of compile-time architecture —
 * if builders are non-deterministic, compiled output drifts.
 */
class BuilderDeterminismTest extends TestCase
{
    /**
     * XmlBuilder::from() must produce identical output for identical input.
     * Tests the md5(serialize()) cache mechanism AND idempotency.
     */
    public function testXmlBuilderIdempotency(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'purpose',
                    'text' => 'Test purpose',
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'text' => 'Test provides block',
                    'single' => false,
                    'child' => [],
                ],
            ],
        ];

        $first = XmlBuilder::from($structure);
        $second = XmlBuilder::from($structure);
        $third = XmlBuilder::from($structure);

        $this->assertSame($first, $second, 'XmlBuilder must be idempotent (run 1 vs 2)');
        $this->assertSame($second, $third, 'XmlBuilder must be idempotent (run 2 vs 3)');
    }

    /**
     * TomlBuilder::from() must produce identical output for identical input.
     */
    public function testTomlBuilderIdempotency(): void
    {
        $data = [
            'name' => 'test',
            'version' => '1.0.0',
            'settings' => [
                'debug' => false,
                'level' => 3,
            ],
        ];

        $first = TomlBuilder::from($data);
        $second = TomlBuilder::from($data);

        $this->assertSame($first, $second, 'TomlBuilder must be idempotent');
    }

    /**
     * XmlBuilder must produce stable ordering — child elements
     * must appear in the same order as the input array.
     */
    public function testXmlBuilderPreservesChildOrder(): void
    {
        $structure = [
            'element' => 'root',
            'single' => false,
            'child' => [
                ['element' => 'alpha', 'single' => true, 'child' => []],
                ['element' => 'beta', 'single' => true, 'child' => []],
                ['element' => 'gamma', 'single' => true, 'child' => []],
            ],
        ];

        $xml = XmlBuilder::from($structure);

        $alphaPos = strpos($xml, '<alpha/>');
        $betaPos = strpos($xml, '<beta/>');
        $gammaPos = strpos($xml, '<gamma/>');

        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($betaPos);
        $this->assertNotFalse($gammaPos);
        $this->assertLessThan($betaPos, $alphaPos, 'alpha must appear before beta');
        $this->assertLessThan($gammaPos, $betaPos, 'beta must appear before gamma');
    }

    /**
     * TomlBuilder must preserve key insertion order for scalars.
     */
    public function testTomlBuilderPreservesKeyOrder(): void
    {
        $data = [
            'zebra' => 'z',
            'alpha' => 'a',
            'middle' => 'm',
        ];

        $toml = TomlBuilder::from($data);

        $zebraPos = strpos($toml, 'zebra');
        $alphaPos = strpos($toml, 'alpha');
        $middlePos = strpos($toml, 'middle');

        $this->assertNotFalse($zebraPos);
        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($middlePos);
        $this->assertLessThan($alphaPos, $zebraPos, 'Keys must appear in insertion order');
        $this->assertLessThan($middlePos, $alphaPos, 'Keys must appear in insertion order');
    }

    /**
     * XmlBuilder double-newline contract: top-level children
     * must be separated by double newlines.
     */
    public function testXmlBuilderDoubleNewlineBetweenTopLevel(): void
    {
        $structure = [
            'element' => 'system',
            'single' => false,
            'child' => [
                [
                    'element' => 'purpose',
                    'text' => 'Purpose text',
                    'single' => false,
                    'child' => [],
                ],
                [
                    'element' => 'provides',
                    'text' => 'Provides text',
                    'single' => false,
                    'child' => [],
                ],
            ],
        ];

        $xml = XmlBuilder::from($structure);

        $this->assertStringContainsString(
            "</purpose>\n\n<provides>",
            $xml,
            'Top-level siblings must be separated by double newline'
        );
    }
}
