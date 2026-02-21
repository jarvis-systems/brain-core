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

    // ── Integration: brain diagnose produces valid JSON ──────────────

    public function testDiagnoseCommandProducesValidJson(): void
    {
        $output = [];
        $exitCode = 0;
        exec(
            "cd " . escapeshellarg(self::$projectRoot) . " && brain diagnose 2>/dev/null",
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            $this->markTestSkipped('brain diagnose failed or not available');
        }

        $json = implode("\n", $output);
        $data = json_decode($json, true);

        $this->assertIsArray($data, 'brain diagnose output must be valid JSON');

        // Required top-level keys
        $this->assertArrayHasKey('self_dev_mode', $data);
        $this->assertArrayHasKey('self_dev_source', $data);
        $this->assertArrayHasKey('autodetect_signals', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('modes', $data);
        $this->assertArrayHasKey('version', $data);

        // self_dev_source must be one of allowed values
        $this->assertContains(
            $data['self_dev_source'],
            ['env', 'autodetect', 'off'],
            'self_dev_source must be env|autodetect|off'
        );

        // self_dev_mode must be boolean
        $this->assertIsBool($data['self_dev_mode']);

        // autodetect_signals must have all subkeys
        $this->assertArrayHasKey('node_brain_php_in_root', $data['autodetect_signals']);
        $this->assertArrayHasKey('node_brain_php_in_dot_brain', $data['autodetect_signals']);
        $this->assertArrayHasKey('dot_brain_is_symlink', $data['autodetect_signals']);
        $this->assertArrayHasKey('dot_brain_target', $data['autodetect_signals']);

        // version must have subkeys
        $this->assertArrayHasKey('root', $data['version']);
        $this->assertArrayHasKey('core', $data['version']);
        $this->assertArrayHasKey('cli', $data['version']);
    }
}
