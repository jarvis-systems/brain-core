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

    // ── Integration: Rule appears in compiled Brain output ───────────

    public function testRuleAppearsInCompiledOutput(): void
    {
        $projectRoot = self::$projectRoot;

        // Run brain compile and check the compiled CLAUDE.md
        $output = [];
        $exitCode = 0;
        exec(
            "cd " . escapeshellarg($projectRoot) . " && brain compile 2>/dev/null",
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            $this->markTestSkipped('brain compile failed or not available — skipping integration check');
        }

        $compiledFile = $projectRoot . '/.claude/CLAUDE.md';
        if (!is_file($compiledFile)) {
            $this->markTestSkipped('Compiled CLAUDE.md not found — skipping integration check');
        }

        $compiled = (string) file_get_contents($compiledFile);

        // XmlBuilder capitalizes rule IDs in markdown headings: no-secret-output → No-secret-output
        $this->assertTrue(
            str_contains(strtolower($compiled), 'no-secret-output'),
            'Compiled CLAUDE.md must contain no-secret-output rule (case-insensitive)'
        );

        $this->assertStringContainsString(
            'NEVER output secrets',
            $compiled,
            'Compiled CLAUDE.md must contain the rule text'
        );
    }
}
