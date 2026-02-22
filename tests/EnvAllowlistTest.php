<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Core;
use PHPUnit\Framework\TestCase;

/**
 * Env allowlist gate: Core::env() blocks unknown keys,
 * passes allowed keys, and allEnv() filters system vars.
 */
class EnvAllowlistTest extends TestCase
{
    /** @var list<string> Environment variables to clean up */
    private array $envCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->envCleanup as $name) {
            putenv($name);
        }
        $this->envCleanup = [];

        parent::tearDown();
    }

    private function setEnv(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $this->envCleanup[] = $name;
    }

    // ──────────────────────────────────────────────
    // isAllowedEnvKey
    // ──────────────────────────────────────────────

    public function testBrainPrefixIsAllowed(): void
    {
        $this->assertTrue(Core::isAllowedEnvKey('BRAIN_COMPILE_DEBUG'));
        $this->assertTrue(Core::isAllowedEnvKey('BRAIN_PROFILE'));
        $this->assertTrue(Core::isAllowedEnvKey('BRAIN_CONFIG_PATH'));
    }

    public function testExplicitKeyIsAllowed(): void
    {
        $this->assertTrue(Core::isAllowedEnvKey('DEBUG'));
        $this->assertTrue(Core::isAllowedEnvKey('debug'));
    }

    public function testSystemVarsBlocked(): void
    {
        $this->assertFalse(Core::isAllowedEnvKey('PATH'));
        $this->assertFalse(Core::isAllowedEnvKey('HOME'));
        $this->assertFalse(Core::isAllowedEnvKey('LD_PRELOAD'));
        $this->assertFalse(Core::isAllowedEnvKey('HTTP_PROXY'));
        $this->assertFalse(Core::isAllowedEnvKey('TERM'));
    }

    // ──────────────────────────────────────────────
    // Core::env() — strict accessor
    // ──────────────────────────────────────────────

    public function testEnvReturnsDefaultForUnknownKey(): void
    {
        // PATH is always set in process env, but Core::env() must block it
        $this->assertNull(Core::env('PATH'));
        $this->assertSame('fallback', Core::env('PATH', 'fallback'));
    }

    public function testEnvReturnsValueForAllowedBrainKey(): void
    {
        $this->setEnv('BRAIN_TEST_ALLOW_' . getmypid(), 'yes');
        $this->assertSame('yes', Core::env('BRAIN_TEST_ALLOW_' . getmypid()));
    }

    public function testEnvReturnsDefaultForMissingAllowedKey(): void
    {
        $key = 'BRAIN_NONEXISTENT_' . uniqid();
        $this->assertNull(Core::env($key));
        $this->assertSame(42, Core::env($key, 42));
    }

    public function testEnvTypeCastsLikGetEnv(): void
    {
        $this->setEnv('BRAIN_TEST_INT_GATE', '99');
        $this->assertSame(99, Core::env('BRAIN_TEST_INT_GATE'));

        $this->setEnv('BRAIN_TEST_BOOL_GATE', 'true');
        $this->assertTrue(Core::env('BRAIN_TEST_BOOL_GATE'));
    }

    public function testEnvIsCaseInsensitive(): void
    {
        $this->setEnv('BRAIN_TEST_CASE_GATE', 'ok');
        $this->assertSame('ok', Core::env('brain_test_case_gate'));
    }

    // ──────────────────────────────────────────────
    // allEnv() filters system vars
    // ──────────────────────────────────────────────

    public function testAllEnvExcludesSystemVars(): void
    {
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        $core = new Core();
        $all = $core->allEnv();

        // System vars must be filtered out
        $this->assertArrayNotHasKey('PATH', $all);
        $this->assertArrayNotHasKey('HOME', $all);
        $this->assertArrayNotHasKey('TERM', $all);

        // Every returned key must pass the allowlist
        foreach (array_keys($all) as $key) {
            $this->assertTrue(
                Core::isAllowedEnvKey($key),
                "allEnv() returned non-allowed key: {$key}"
            );
        }
    }

    public function testAllEnvIncludesBrainPrefixedVars(): void
    {
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        $uniqueKey = 'BRAIN_TEST_ALLENV_FILTER_' . strtoupper(substr(uniqid(), -4));
        $this->setEnv($uniqueKey, 'included');

        $core = new Core();
        $all = $core->allEnv();

        $this->assertArrayHasKey($uniqueKey, $all);
        $this->assertSame('included', $all[$uniqueKey]);
    }
}
