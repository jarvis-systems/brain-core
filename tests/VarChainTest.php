<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Abstracts\ArchitectureAbstract;
use BrainCore\Core;
use BrainCore\Support\Brain;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Stub for testing ArchitectureAbstract var resolution chain.
 */
class StubArchitecture extends ArchitectureAbstract
{
    /**
     * Method hook: transforms the resolved value for 'hooked_var'.
     */
    protected function hooked_var(mixed $value): mixed
    {
        return 'hook:' . ($value ?? 'null');
    }
}

/**
 * ArchitectureAbstract variable resolution chain contracts.
 *
 * Enterprise invariant: var() resolution order must be:
 *   ENV → Variable Store → Meta → Method Hook → Default
 *
 * This chain is the foundation of the Brain variable system.
 * Any change in resolution order breaks all compiled configurations.
 */
class VarChainTest extends TestCase
{
    private Core $core;
    private StubArchitecture $stub;

    /** @var list<string> Environment variables to clean up */
    private array $envCleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        // Bootstrap minimal container for Brain facade
        $container = new Container();
        $this->core = new Core();
        $container->instance(Core::class, $this->core);
        Container::setInstance($container);
        Brain::setFacadeApplication($container);
        Brain::clearResolvedInstances();

        $this->stub = new StubArchitecture();
    }

    protected function tearDown(): void
    {
        foreach ($this->envCleanup as $name) {
            putenv($name);
        }
        $this->envCleanup = [];

        Brain::clearResolvedInstances();
        Brain::setFacadeApplication(null);

        parent::tearDown();
    }

    private function setEnv(string $name, string $value): void
    {
        putenv(strtoupper($name) . "={$value}");
        $this->envCleanup[] = strtoupper($name);
    }

    // ──────────────────────────────────────────────
    // Resolution order: ENV wins over everything
    // ──────────────────────────────────────────────

    public function testVarResolvesFromEnvFirst(): void
    {
        $this->setEnv('TEST_PRIO', 'from-env');
        $this->core->setVariable('test_prio', 'from-store');

        $this->assertSame('from-env', $this->stub->var('test_prio'));
    }

    // ──────────────────────────────────────────────
    // Resolution order: Variable Store when no ENV
    // ──────────────────────────────────────────────

    public function testVarResolvesFromStoreWhenNoEnv(): void
    {
        $this->core->setVariable('store_only', 'from-store');

        $this->assertSame('from-store', $this->stub->var('store_only'));
    }

    // ──────────────────────────────────────────────
    // Resolution order: Meta when no ENV or Store
    // ──────────────────────────────────────────────

    public function testVarResolvesFromMetaWhenNoStoreOrEnv(): void
    {
        $this->stub->setMeta('meta_var', 'from-meta');

        $this->assertSame('from-meta', $this->stub->var('meta_var'));
    }

    // ──────────────────────────────────────────────
    // Resolution order: Method hook transforms value
    // ──────────────────────────────────────────────

    public function testVarCallsMethodHookWhenExists(): void
    {
        // StubArchitecture has hooked_var() method
        // No env, no store → meta default used → hook transforms it
        $this->assertSame('hook:null', $this->stub->var('hooked_var'));
    }

    public function testVarMethodHookReceivesMetaValue(): void
    {
        $this->stub->setMeta('hooked_var', 'meta-input');

        $this->assertSame('hook:meta-input', $this->stub->var('hooked_var'));
    }

    public function testVarMethodHookSkippedWhenEnvPresent(): void
    {
        // ENV takes priority — method hook should NOT be called
        $this->setEnv('HOOKED_VAR', 'env-value');

        $this->assertSame('env-value', $this->stub->var('hooked_var'));
    }

    // ──────────────────────────────────────────────
    // Default value
    // ──────────────────────────────────────────────

    public function testVarReturnsDefaultWhenNothingMatches(): void
    {
        $this->assertNull($this->stub->var('no_such_var'));
        $this->assertSame('fallback', $this->stub->var('no_such_var', 'fallback'));
    }

    // ──────────────────────────────────────────────
    // varIs — strict and loose comparison
    // ──────────────────────────────────────────────

    public function testVarIsStrictComparison(): void
    {
        $this->core->setVariable('strict_test', 1);

        $this->assertTrue($this->stub->varIs('strict_test', 1, true));
        $this->assertFalse($this->stub->varIs('strict_test', '1', true));
        $this->assertFalse($this->stub->varIs('strict_test', true, true));
    }

    public function testVarIsLooseComparison(): void
    {
        $this->core->setVariable('loose_test', 1);

        $this->assertTrue($this->stub->varIs('loose_test', 1, false));
        $this->assertTrue($this->stub->varIs('loose_test', '1', false));
        $this->assertTrue($this->stub->varIs('loose_test', true, false));
    }

    // ──────────────────────────────────────────────
    // varIsPositive / varIsNegative
    // ──────────────────────────────────────────────

    public function testVarIsPositiveMatchesTruthyValues(): void
    {
        // true (bool) — positive
        $this->core->setVariable('pos_bool', true);
        $this->assertTrue($this->stub->varIsPositive('pos_bool'));

        // 1 (int) — positive (1 == true is true)
        $this->core->setVariable('pos_int', 1);
        $this->assertTrue($this->stub->varIsPositive('pos_int'));

        // "1" (string) — positive ("1" == true is true)
        $this->core->setVariable('pos_str', '1');
        $this->assertTrue($this->stub->varIsPositive('pos_str'));
    }

    public function testVarIsPositiveRejectsFalsyValues(): void
    {
        $this->core->setVariable('neg_bool', false);
        $this->assertFalse($this->stub->varIsPositive('neg_bool'));

        $this->core->setVariable('neg_zero', 0);
        $this->assertFalse($this->stub->varIsPositive('neg_zero'));

        $this->core->setVariable('neg_empty', '');
        $this->assertFalse($this->stub->varIsPositive('neg_empty'));
    }

    public function testVarIsNegativeMatchesFalsyValues(): void
    {
        $this->core->setVariable('neg_test', false);
        $this->assertTrue($this->stub->varIsNegative('neg_test'));

        $this->core->setVariable('neg_zero', 0);
        $this->assertTrue($this->stub->varIsNegative('neg_zero'));

        $this->core->setVariable('neg_empty', '');
        $this->assertTrue($this->stub->varIsNegative('neg_empty'));
    }

    public function testVarIsNegativeRejectsTruthyValues(): void
    {
        $this->core->setVariable('pos_test', true);
        $this->assertFalse($this->stub->varIsNegative('pos_test'));

        $this->core->setVariable('pos_one', 1);
        $this->assertFalse($this->stub->varIsNegative('pos_one'));
    }

    // ──────────────────────────────────────────────
    // allVars — merge variables + env
    // ──────────────────────────────────────────────

    public function testAllVarsMergesVariablesAndEnv(): void
    {
        $prefix = 'BRAIN_VARCHAIN_' . strtoupper(substr(uniqid(), -4));
        $this->core->setVariable($prefix . '_STORE', 'from-store');
        $this->setEnv($prefix . '_ENV', 'from-env');

        $all = $this->stub->allVars($prefix);

        $this->assertArrayHasKey($prefix . '_STORE', $all);
        $this->assertArrayHasKey($prefix . '_ENV', $all);
    }

    // ──────────────────────────────────────────────
    // groupVars — prefix stripping
    // ──────────────────────────────────────────────

    public function testGroupVarsStripsPrefix(): void
    {
        $this->core->setVariable('MCP_TIMEOUT', 30);
        $this->core->setVariable('MCP_RETRIES', 3);
        $this->core->setVariable('OTHER', 'skip');

        $group = $this->stub->groupVars('MCP');

        $this->assertArrayHasKey('TIMEOUT', $group);
        $this->assertArrayHasKey('RETRIES', $group);
        $this->assertArrayNotHasKey('OTHER', $group);
        $this->assertSame(30, $group['TIMEOUT']);
    }

    // ──────────────────────────────────────────────
    // disableByDefault
    // ──────────────────────────────────────────────

    public function testDisableByDefaultReturnsFalse(): void
    {
        $this->assertFalse(StubArchitecture::disableByDefault());
    }

    // ──────────────────────────────────────────────
    // Determinism
    // ──────────────────────────────────────────────

    public function testVarResolutionDeterminism(): void
    {
        $this->core->setVariable('det_var', 'stable');

        $first = $this->stub->var('det_var');
        $second = $this->stub->var('det_var');
        $this->assertSame($first, $second, 'var() must return same value on repeated calls');
    }
}
