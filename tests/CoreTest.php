<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Core;
use PHPUnit\Framework\TestCase;

/**
 * Core variable store, env resolution, and path contracts.
 *
 * Enterprise invariant: Core is the single source of truth for
 * compile-time variables and environment access. All Brain facade
 * calls resolve here. Variable semantics must be locked.
 */
class CoreTest extends TestCase
{
    private Core $core;

    /** @var list<string> Environment variables to clean up */
    private array $envCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        $this->core = new Core();
    }

    protected function tearDown(): void
    {
        foreach ($this->envCleanup as $name) {
            putenv($name);
        }
        $this->envCleanup = [];

        parent::tearDown();
    }

    /**
     * Set env var and track for cleanup.
     */
    private function setEnv(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $this->envCleanup[] = $name;
    }

    // ──────────────────────────────────────────────
    // Variable store: set / get / merge
    // ──────────────────────────────────────────────

    public function testSetAndGetVariable(): void
    {
        $this->core->setVariable('agent', 'claude');
        $this->assertSame('claude', $this->core->getVariable('agent'));
    }

    public function testGetVariableFallsBackToUpperCase(): void
    {
        $this->core->setVariable('MY_VAR', 'found');
        // Lookup by lowercase should find UPPER_CASE key
        $this->assertSame('found', $this->core->getVariable('my_var'));
    }

    public function testGetVariableExactKeyTakesPrecedence(): void
    {
        $this->core->setVariable('key', 'exact');
        $this->core->setVariable('KEY', 'upper');
        $this->assertSame('exact', $this->core->getVariable('key'));
    }

    public function testGetVariableMissingReturnsDefault(): void
    {
        $this->assertNull($this->core->getVariable('nonexistent'));
        $this->assertSame('fallback', $this->core->getVariable('nonexistent', 'fallback'));
    }

    public function testGetVariableClosureDefault(): void
    {
        $result = $this->core->getVariable('missing', fn () => 'computed');
        $this->assertSame('computed', $result);
    }

    public function testMergeVariablesOverwritesSameKeys(): void
    {
        $this->core->setVariable('a', 'old');
        $this->core->mergeVariables(['a' => 'new', 'b' => 'added']);

        $this->assertSame('new', $this->core->getVariable('a'));
        $this->assertSame('added', $this->core->getVariable('b'));
    }

    public function testMergeVariablesMultipleArraysLaterWins(): void
    {
        $this->core->mergeVariables(
            ['x' => 'first'],
            ['x' => 'second'],
            ['x' => 'third']
        );

        $this->assertSame('third', $this->core->getVariable('x'));
    }

    public function testGetVariablesReturnsAll(): void
    {
        $this->core->setVariable('a', 1);
        $this->core->setVariable('b', 2);

        $vars = $this->core->getVariables();
        $this->assertArrayHasKey('a', $vars);
        $this->assertArrayHasKey('b', $vars);
        $this->assertSame(1, $vars['a']);
    }

    public function testAllVariablesFiltersByPrefix(): void
    {
        $this->core->setVariable('BRAIN_MODE', 'strict');
        $this->core->setVariable('BRAIN_LEVEL', 'high');
        $this->core->setVariable('OTHER', 'skip');

        $filtered = $this->core->allVariables('BRAIN_');
        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey('BRAIN_MODE', $filtered);
        $this->assertArrayHasKey('BRAIN_LEVEL', $filtered);
        $this->assertArrayNotHasKey('OTHER', $filtered);
    }

    public function testAllVariablesNullReturnsAll(): void
    {
        $this->core->setVariable('a', 1);
        $this->core->setVariable('b', 2);

        $all = $this->core->allVariables();
        $this->assertCount(2, $all);
    }

    // ──────────────────────────────────────────────
    // basePath
    // ──────────────────────────────────────────────

    public function testBasePathRelativeMode(): void
    {
        // Relative mode: no getcwd() dependency
        $this->assertSame('src/Core.php', $this->core->basePath('src/Core.php', true));
        $this->assertSame('', $this->core->basePath('', true));
    }

    public function testBasePathWithArrayPath(): void
    {
        $result = $this->core->basePath(['src', 'Core.php'], true);
        $this->assertSame('src' . DS . 'Core.php', $result);
    }

    public function testBasePathWithArrayFiltersEmptySegments(): void
    {
        $result = $this->core->basePath(['', 'src', '', 'Core.php'], true);
        $this->assertSame('src' . DS . 'Core.php', $result);
    }

    public function testBasePathAbsoluteStartsWithCwd(): void
    {
        $cwd = getcwd();
        $this->assertNotFalse($cwd);

        $result = $this->core->basePath('file.php');
        $this->assertStringStartsWith($cwd, $result);
        $this->assertStringEndsWith('file.php', $result);
    }

    // ──────────────────────────────────────────────
    // version
    // ──────────────────────────────────────────────

    public function testVersionReadsComposerJson(): void
    {
        $version = $this->core->version();

        // Read expected version from composer.json (single source of truth)
        $composerPath = dirname(__DIR__) . '/composer.json';
        $expected = json_decode(file_get_contents($composerPath), true)['version'];

        $this->assertSame($expected, $version);
        $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', $version);
    }

    public function testVersionCachesResult(): void
    {
        $first = $this->core->version();
        $second = $this->core->version();
        $this->assertSame($first, $second, 'version() must cache and return stable result');
    }

    // ──────────────────────────────────────────────
    // getEnv — type casting
    // ──────────────────────────────────────────────

    public function testGetEnvReturnsNullForMissing(): void
    {
        $this->assertNull($this->core->getEnv('BRAIN_TEST_NONEXISTENT_' . uniqid()));
    }

    public function testGetEnvCastsStringNull(): void
    {
        $this->setEnv('BRAIN_TEST_NULL', 'null');
        $this->assertNull($this->core->getEnv('brain_test_null'));
    }

    public function testGetEnvCastsInteger(): void
    {
        $this->setEnv('BRAIN_TEST_INT', '42');
        $this->assertSame(42, $this->core->getEnv('brain_test_int'));
    }

    public function testGetEnvCastsFloat(): void
    {
        $this->setEnv('BRAIN_TEST_FLOAT', '3.14');
        $this->assertSame(3.14, $this->core->getEnv('brain_test_float'));
    }

    public function testGetEnvCastsBoolTrue(): void
    {
        $this->setEnv('BRAIN_TEST_BOOL_T', 'true');
        $this->assertTrue($this->core->getEnv('brain_test_bool_t'));

        $this->setEnv('BRAIN_TEST_BOOL_T2', 'True');
        $this->assertTrue($this->core->getEnv('brain_test_bool_t2'));
    }

    public function testGetEnvCastsBoolFalse(): void
    {
        $this->setEnv('BRAIN_TEST_BOOL_F', 'false');
        $this->assertFalse($this->core->getEnv('brain_test_bool_f'));
    }

    public function testGetEnvCastsJsonArray(): void
    {
        $this->setEnv('BRAIN_TEST_JSON_ARR', '[1,2,3]');
        $this->assertSame([1, 2, 3], $this->core->getEnv('brain_test_json_arr'));
    }

    public function testGetEnvCastsJsonObject(): void
    {
        $this->setEnv('BRAIN_TEST_JSON_OBJ', '{"a":1}');
        $this->assertSame(['a' => 1], $this->core->getEnv('brain_test_json_obj'));
    }

    public function testGetEnvReturnsPlainString(): void
    {
        $this->setEnv('BRAIN_TEST_STR', 'hello world');
        $this->assertSame('hello world', $this->core->getEnv('brain_test_str'));
    }

    public function testGetEnvUppercasesName(): void
    {
        $this->setEnv('BRAIN_TEST_CASE', 'value');
        // Lowercase input should resolve uppercase env
        $this->assertSame('value', $this->core->getEnv('brain_test_case'));
    }

    // ──────────────────────────────────────────────
    // hasEnv / isDebug / allEnv
    // ──────────────────────────────────────────────

    public function testHasEnv(): void
    {
        $key = 'BRAIN_TEST_HAS_' . uniqid();
        $this->assertFalse($this->core->hasEnv($key));

        $this->setEnv(strtoupper($key), 'yes');
        $this->assertTrue($this->core->hasEnv($key));
    }

    public function testIsDebugRespondsToEnvVars(): void
    {
        // Neither set — not debug
        $this->assertFalse($this->core->isDebug());

        // BRAIN_CORE_DEBUG
        $this->setEnv('BRAIN_CORE_DEBUG', '1');
        $this->assertTrue($this->core->isDebug());
        putenv('BRAIN_CORE_DEBUG');

        // DEBUG
        $this->setEnv('DEBUG', '1');
        $this->assertTrue($this->core->isDebug());
    }

    public function testAllEnvFiltersByPrefix(): void
    {
        $prefix = 'BRAIN_TEST_ALLENV_' . strtoupper(substr(uniqid(), -4));
        $this->setEnv($prefix . '_A', 'one');
        $this->setEnv($prefix . '_B', 'two');

        $filtered = $this->core->allEnv($prefix);
        $this->assertCount(2, $filtered);
        $this->assertArrayHasKey($prefix . '_A', $filtered);
        $this->assertArrayHasKey($prefix . '_B', $filtered);
    }

    // ──────────────────────────────────────────────
    // Env allowlist + compile-resolve split
    // ──────────────────────────────────────────────

    public function testEnvAllowlistBlocksGenericKeys(): void
    {
        $this->setEnv('HOME_TEST_SENTINEL', '/tmp/nope');
        $this->setEnv('PATH_TEST_SENTINEL', '/usr/bin');

        // Static filtered accessor must reject non-allowlisted keys
        $this->assertNull(Core::env('HOME_TEST_SENTINEL'));
        $this->assertNull(Core::env('PATH_TEST_SENTINEL'));
    }

    public function testEnvAllowlistPassesProjectKeys(): void
    {
        $this->setEnv('LANGUAGE', 'Ukrainian');
        $this->setEnv('STRICT_MODE', 'paranoid');
        $this->setEnv('COGNITIVE_LEVEL', 'exhaustive');
        $this->setEnv('VERBOSITY', 'medium');
        $this->setEnv('SELF_DEV_MODE', 'true');
        $this->setEnv('QUALITY_COMMAND_TEST', 'composer test');
        $this->setEnv('QUALITY_COMMAND_PHPSTAN', 'composer analyse');

        $this->assertSame('Ukrainian', Core::env('LANGUAGE'));
        $this->assertSame('paranoid', Core::env('STRICT_MODE'));
        $this->assertSame('exhaustive', Core::env('COGNITIVE_LEVEL'));
        $this->assertSame('medium', Core::env('VERBOSITY'));
        $this->assertTrue(Core::env('SELF_DEV_MODE'));
        $this->assertSame('composer test', Core::env('QUALITY_COMMAND_TEST'));
        $this->assertSame('composer analyse', Core::env('QUALITY_COMMAND_PHPSTAN'));
    }

    public function testEnvAllowlistPassesNamespacePrefixes(): void
    {
        $this->setEnv('MCP_TEST_NODE_DISABLE', '1');
        $this->setEnv('AGENTS_TEST_MASTER_ENABLE', '1');

        $this->assertSame(1, Core::env('MCP_TEST_NODE_DISABLE'));
        $this->assertSame(1, Core::env('AGENTS_TEST_MASTER_ENABLE'));
    }

    public function testAllEnvIncludesProjectKeys(): void
    {
        $this->setEnv('LANGUAGE', 'Ukrainian');
        $this->setEnv('STRICT_MODE', 'paranoid');

        $all = $this->core->allEnv();
        $this->assertArrayHasKey('LANGUAGE', $all);
        $this->assertArrayHasKey('STRICT_MODE', $all);
    }

    public function testAllEnvExcludesGenericSystemVars(): void
    {
        // HOME/PATH are typically set; they must NOT appear in allEnv
        $all = $this->core->allEnv();
        $this->assertArrayNotHasKey('HOME', $all);
        $this->assertArrayNotHasKey('PATH', $all);
        $this->assertArrayNotHasKey('USER', $all);
        $this->assertArrayNotHasKey('SHELL', $all);
    }

    public function testResolveCompileEnvReadsArbitraryKeys(): void
    {
        $key = 'XYZZY_COMPILE_TEST_' . strtoupper(substr(uniqid(), -4));
        $this->setEnv($key, 'magic');

        // resolveCompileEnv is unfiltered — must read any process env
        $this->assertSame('magic', $this->core->resolveCompileEnv($key));
    }

    public function testHasCompileEnvReadsArbitraryKeys(): void
    {
        $key = 'XYZZY_HAS_TEST_' . strtoupper(substr(uniqid(), -4));
        $this->assertFalse($this->core->hasCompileEnv($key));

        $this->setEnv($key, 'present');
        $this->assertTrue($this->core->hasCompileEnv($key));
    }

    public function testResolveCompileEnvReturnsNullForMissing(): void
    {
        $this->assertNull($this->core->resolveCompileEnv('BRAIN_NONEXISTENT_' . uniqid()));
    }

    public function testDeprecatedGetEnvDelegatesToResolveCompileEnv(): void
    {
        $key = 'BRAIN_COMPAT_TEST_' . strtoupper(substr(uniqid(), -4));
        $this->setEnv($key, '42');

        // Deprecated wrapper must return same result
        $this->assertSame(
            $this->core->resolveCompileEnv($key),
            $this->core->getEnv($key)
        );
    }

    // ──────────────────────────────────────────────
    // CompileDto
    // ──────────────────────────────────────────────

    public function testCompileDtoSetAndGet(): void
    {
        $this->assertNull($this->core->getCurrentCompileDto());

        // We can't easily create a Dto instance without a concrete subclass,
        // so test the null roundtrip contract
        $this->core->setCurrentCompileDto(null);
        $this->assertNull($this->core->getCurrentCompileDto());
    }

    // ──────────────────────────────────────────────
    // Determinism
    // ──────────────────────────────────────────────

    public function testVariableSystemDeterminism(): void
    {
        $this->core->setVariable('det_test', 'value');

        $first = $this->core->getVariable('det_test');
        $second = $this->core->getVariable('det_test');
        $this->assertSame($first, $second);

        $vars1 = $this->core->allVariables('det_');
        $vars2 = $this->core->allVariables('det_');
        $this->assertSame($vars1, $vars2);
    }
}
