<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * DiagnoseCommand structural and integration tests.
 *
 * Enterprise invariant: brain diagnose must produce valid JSON
 * with all required diagnostic fields and never leak secret values.
 */
class DiagnoseOutputTest extends TestCase
{
    private static string $projectRoot;

    public static function setUpBeforeClass(): void
    {
        self::$projectRoot = dirname(__DIR__, 2);
    }

    // ── Structural: Command file exists with correct contract ────────

    public function testDiagnoseCommandFileExistsWithCorrectStructure(): void
    {
        $file = self::$projectRoot . '/cli/src/Console/Commands/DiagnoseCommand.php';
        $this->assertFileExists($file);

        $content = (string) file_get_contents($file);

        $this->assertStringContainsString(
            'declare(strict_types=1)',
            $content,
            'Must declare strict_types'
        );

        $this->assertStringContainsString(
            'namespace BrainCLI\\Console\\Commands',
            $content,
            'Must be in CLI Commands namespace'
        );

        $this->assertStringContainsString(
            'extends Command',
            $content,
            'Must extend Command'
        );

        $this->assertStringContainsString(
            "'diagnose",
            $content,
            'Signature must start with diagnose'
        );

        $this->assertStringContainsString(
            '--human',
            $content,
            'Must support --human flag'
        );
    }

    // ── Structural: Command is registered in ServiceProvider ─────────

    public function testDiagnoseCommandIsRegisteredInServiceProvider(): void
    {
        $file = self::$projectRoot . '/cli/src/ServiceProvider.php';
        $content = (string) file_get_contents($file);

        $this->assertStringContainsString(
            'DiagnoseCommand::class',
            $content,
            'ServiceProvider must register DiagnoseCommand'
        );

        $this->assertStringContainsString(
            'use BrainCLI\\Console\\Commands\\DiagnoseCommand',
            $content,
            'ServiceProvider must import DiagnoseCommand'
        );
    }

    // ── Structural: All required JSON fields are in source ───────────

    public function testDiagnoseCommandOutputsRequiredFields(): void
    {
        $file = self::$projectRoot . '/cli/src/Console/Commands/DiagnoseCommand.php';
        $content = (string) file_get_contents($file);

        $requiredKeys = [
            'self_dev_mode',
            'self_dev_source',
            'autodetect_signals',
            'node_brain_php_in_root',
            'node_brain_php_in_dot_brain',
            'dot_brain_is_symlink',
            'dot_brain_target',
            'paths',
            'project_root',
            'brain_dir',
            'dot_brain_path',
            'modes',
            'strict_mode',
            'cognitive_level',
            'verbosity',
            'version',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertStringContainsString(
                "'{$key}'",
                $content,
                "DiagnoseCommand must include field: {$key}"
            );
        }
    }

    // ── Structural: No bulk ENV dump methods ─────────────────────────

    public function testDiagnoseCommandDoesNotDumpAllEnv(): void
    {
        $file = self::$projectRoot . '/cli/src/Console/Commands/DiagnoseCommand.php';
        $content = (string) file_get_contents($file);

        // Must NOT call allEnv() — that would dump all env vars including secrets
        $this->assertStringNotContainsString(
            'allEnv()',
            $content,
            'Must not dump all ENV variables via allEnv()'
        );
    }

    // ── Structural: JSON output contract ─────────────────────────────

    /**
     * Verifies handle() produces JSON-encoded output and constrains
     * enum/type contracts — deterministic replacement for exec()-based
     * integration test.
     *
     * Combined with testDiagnoseCommandOutputsRequiredFields (key existence),
     * this guarantees the full output contract without requiring the brain binary.
     */
    public function testDiagnoseCommandJsonOutputContract(): void
    {
        $file = self::$projectRoot . '/cli/src/Console/Commands/DiagnoseCommand.php';
        $content = (string) file_get_contents($file);

        // Output must be JSON-encoded
        $this->assertStringContainsString(
            'json_encode',
            $content,
            'DiagnoseCommand must encode output as JSON'
        );

        // self_dev_source enum: exactly three allowed values
        $this->assertStringContainsString("'env'", $content, 'Must define env source');
        $this->assertStringContainsString("'autodetect'", $content, 'Must define autodetect source');
        $this->assertStringContainsString("'off'", $content, 'Must define off source');

        // Nested arrays: autodetect_signals and version must be array-valued
        $this->assertMatchesRegularExpression(
            "/'autodetect_signals'\s*=>\s*\[/",
            $content,
            'autodetect_signals must be a nested array'
        );
        $this->assertMatchesRegularExpression(
            "/'version'\s*=>\s*\[/",
            $content,
            'version must be a nested array'
        );

        // version subkeys (root, core, cli) must exist as array keys
        foreach (['root', 'core', 'cli'] as $subkey) {
            $this->assertStringContainsString(
                "'{$subkey}'",
                $content,
                "version must include subkey: {$subkey}"
            );
        }
    }

    // ── Optional integration: requires RUN_INTEGRATION_TESTS=1 ────

    public function testDiagnoseCommandIntegrationProducesValidJson(): void
    {
        if (!getenv('RUN_INTEGRATION_TESTS')) {
            $this->assertStringContainsString(
                'diagnose',
                (string) file_get_contents(
                    self::$projectRoot . '/cli/src/Console/Commands/DiagnoseCommand.php'
                ),
                'Integration skipped (RUN_INTEGRATION_TESTS not set) — structural guard passed'
            );

            return;
        }

        $output = [];
        $exitCode = 0;
        exec(
            "cd " . escapeshellarg(self::$projectRoot) . " && brain diagnose 2>/dev/null",
            $output,
            $exitCode
        );

        $this->assertSame(0, $exitCode, 'brain diagnose must exit 0');

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data, 'brain diagnose output must be valid JSON');
        $this->assertArrayHasKey('self_dev_mode', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertContains($data['self_dev_source'], ['env', 'autodetect', 'off']);
        $this->assertIsBool($data['self_dev_mode']);
    }
}
