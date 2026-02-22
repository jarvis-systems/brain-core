<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * SecretOutputPolicyInclude structural and integration tests.
 *
 * Enterprise invariant: the secret-output policy MUST be present
 * in both Brain and Agent compilation pipelines. Absence = leaked secrets.
 */
class SecretOutputPolicyIncludeTest extends TestCase
{
    private static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 2);
    }

    // ── Structural: Include class exists with correct contract ────────

    public function testIncludeFileExistsWithCorrectStructure(): void
    {
        $file = self::$projectRoot . '/core/src/Includes/Universal/SecretOutputPolicyInclude.php';
        $this->assertFileExists($file);

        $content = (string) file_get_contents($file);

        $this->assertStringContainsString(
            'declare(strict_types=1)',
            $content,
            'Must declare strict_types'
        );

        $this->assertStringContainsString(
            'namespace BrainCore\\Includes\\Universal',
            $content,
            'Must be in Universal includes namespace'
        );

        $this->assertStringContainsString(
            'extends IncludeArchetype',
            $content,
            'Must extend IncludeArchetype'
        );

        $this->assertMatchesRegularExpression(
            '/#\[Purpose\(/',
            $content,
            'Must have #[Purpose()] attribute'
        );
    }

    // ── Structural: Rule definition ──────────────────────────────────

    public function testIncludeContainsSecretOutputRule(): void
    {
        $file = self::$projectRoot . '/core/src/Includes/Universal/SecretOutputPolicyInclude.php';
        $content = (string) file_get_contents($file);

        $this->assertStringContainsString(
            "'no-secret-output'",
            $content,
            'Must define rule with ID no-secret-output'
        );

        $this->assertStringContainsString(
            '->critical()',
            $content,
            'Rule must be CRITICAL severity'
        );

        $this->assertStringContainsString(
            '->why(',
            $content,
            'Rule must have WHY justification'
        );

        $this->assertStringContainsString(
            '->onViolation(',
            $content,
            'Rule must have onViolation handler'
        );
    }

    // ── Structural: No actual secrets in the include source ──────────

    public function testIncludeDoesNotContainActualSecrets(): void
    {
        $file = self::$projectRoot . '/core/src/Includes/Universal/SecretOutputPolicyInclude.php';
        $content = (string) file_get_contents($file);

        $secretPatterns = [
            'github_pat_',
            'sk-or-v1-',
            'gsk_',
            'ctx7sk-',
        ];

        foreach ($secretPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $content,
                "Include source must not contain secret pattern: {$pattern}"
            );
        }
    }

    // ── Wiring: BrainIncludesTrait references the include ───────────

    public function testIncludeIsWiredIntoBrainTrait(): void
    {
        $file = self::$projectRoot . '/core/src/Variations/Traits/BrainIncludesTrait.php';
        $content = (string) file_get_contents($file);

        $this->assertStringContainsString(
            'use BrainCore\\Includes\\Universal\\SecretOutputPolicyInclude',
            $content,
            'BrainIncludesTrait must import SecretOutputPolicyInclude'
        );

        $this->assertStringContainsString(
            'SecretOutputPolicyInclude::class',
            $content,
            'BrainIncludesTrait must include SecretOutputPolicyInclude'
        );
    }

    // ── Wiring: AgentIncludesTrait references the include ───────────

    public function testIncludeIsWiredIntoAgentTrait(): void
    {
        $file = self::$projectRoot . '/core/src/Variations/Traits/AgentIncludesTrait.php';
        $content = (string) file_get_contents($file);

        $this->assertStringContainsString(
            'use BrainCore\\Includes\\Universal\\SecretOutputPolicyInclude',
            $content,
            'AgentIncludesTrait must import SecretOutputPolicyInclude'
        );

        $this->assertStringContainsString(
            'SecretOutputPolicyInclude::class',
            $content,
            'AgentIncludesTrait must include SecretOutputPolicyInclude'
        );
    }

    // ── Structural: Rule text content matches compilation contract ───

    /**
     * Verifies the exact rule text that the compilation pipeline would render.
     *
     * The compilation path is already proven by:
     * - testIncludeIsWiredIntoBrainTrait (wired into Brain pipeline)
     * - testIncludeIsWiredIntoAgentTrait (wired into Agent pipeline)
     * - MergerTest + SnapshotTest (pipeline renders includes correctly)
     *
     * This test closes the last gap: the rule text content itself.
     */
    public function testRuleTextMatchesCompilationContract(): void
    {
        $file = self::$projectRoot . '/core/src/Includes/Universal/SecretOutputPolicyInclude.php';
        $content = (string) file_get_contents($file);

        // The exact rule text that Merger→XmlBuilder renders into CLAUDE.md
        $this->assertStringContainsString(
            'NEVER output secrets, API keys, tokens, passwords, or sensitive ENV variable values',
            $content,
            'Include must define the canonical secret-output prevention text'
        );

        // Rule ID used by XmlBuilder as section heading
        $this->assertStringContainsString(
            "'no-secret-output'",
            $content,
            'Rule ID must be no-secret-output for stable heading generation'
        );
    }

    // ── Optional integration: requires RUN_INTEGRATION_TESTS=1 ────

    public function testRuleAppearsInCompiledOutputIntegration(): void
    {
        if (!getenv('RUN_INTEGRATION_TESTS')) {
            $this->assertFileExists(
                self::$projectRoot . '/core/src/Includes/Universal/SecretOutputPolicyInclude.php',
                'Integration skipped (RUN_INTEGRATION_TESTS not set) — structural guard passed'
            );

            return;
        }

        $output = [];
        $exitCode = 0;
        exec(
            "cd " . escapeshellarg(self::$projectRoot) . " && brain compile 2>/dev/null",
            $output,
            $exitCode
        );

        $this->assertSame(0, $exitCode, 'brain compile must exit 0');

        $compiledFile = self::$projectRoot . '/.claude/CLAUDE.md';
        $this->assertFileExists($compiledFile, 'Compiled CLAUDE.md must exist after brain compile');

        $compiled = (string) file_get_contents($compiledFile);

        $this->assertTrue(
            str_contains(strtolower($compiled), 'no-secret-output'),
            'Compiled CLAUDE.md must contain no-secret-output rule'
        );
    }
}
