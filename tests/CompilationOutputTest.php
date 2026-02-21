<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Compilation\BrainCLI;
use BrainCore\Compilation\Operator;
use BrainCore\Compilation\Store;
use PHPUnit\Framework\TestCase;

/**
 * Proof Pack: Compilation Output Format Invariants.
 *
 * Enterprise invariant: pseudo-syntax operators must produce
 * stable, deterministic output that LLMs can parse consistently.
 * Format drift = broken agent behavior.
 */
class CompilationOutputTest extends TestCase
{
    /**
     * Store::as() must produce STORE-AS($VAR = value) format.
     */
    public function testStoreAsFormat(): void
    {
        $this->assertSame('STORE-AS($NAME)', Store::as('name'));
        $this->assertSame('STORE-AS($NAME = hello)', Store::as('name', 'hello'));
        $this->assertSame('STORE-AS($RESULT = a + b)', Store::as('result', 'a', 'b'));
    }

    /**
     * Store::get() must produce STORE-GET($VAR) format.
     */
    public function testStoreGetFormat(): void
    {
        $this->assertSame('STORE-GET($NAME)', Store::get('name'));
        $this->assertSame('STORE-GET($RESULT)', Store::get('result'));
    }

    /**
     * Store::var() must uppercase and prefix with $.
     */
    public function testStoreVarNormalization(): void
    {
        $this->assertSame('$MY_VAR', Store::var('my_var'));
        $this->assertSame('$MY_VAR', Store::var('$my_var'), 'Must handle existing $ prefix');
        $this->assertSame('$TEST', Store::var('test'));
    }

    /**
     * Operator::if() must produce correct inline format for short conditions.
     */
    public function testOperatorIfInlineFormat(): void
    {
        $result = Operator::if('x > 0', 'proceed');

        $this->assertStringContainsString('IF(x > 0)', $result);
        $this->assertStringContainsString('proceed', $result);
        $this->assertStringNotContainsString('END-IF', $result, 'Short if must be inline');
    }

    /**
     * Operator::forEach() must produce correct format.
     */
    public function testOperatorForEachFormat(): void
    {
        $result = Operator::forEach('item in list', 'process item');

        $this->assertStringContainsString('FOREACH(item in list)', $result);
        $this->assertStringContainsString('process item', $result);
    }

    /**
     * Operator::task() must wrap multi-line in TASK/END-TASK block.
     */
    public function testOperatorTaskBlockFormat(): void
    {
        $result = Operator::task('step one', 'step two', 'step three');

        $this->assertStringContainsString('TASK', $result);
        $this->assertStringContainsString('step one', $result);
        $this->assertStringContainsString('step two', $result);
        $this->assertStringContainsString('END-TASK', $result);
    }

    /**
     * Operator::verify() must produce VERIFY-SUCCESS format.
     */
    public function testOperatorVerifyFormat(): void
    {
        $result = Operator::verify('all tests pass', 'no errors');

        $this->assertStringStartsWith('VERIFY-SUCCESS(', $result);
        $this->assertStringContainsString('all tests pass', $result);
    }

    /**
     * Operator::validate() must produce VALIDATE with optional FAILS.
     */
    public function testOperatorValidateFormat(): void
    {
        $withoutFails = Operator::validate('condition');
        $this->assertSame('VALIDATE(condition)', $withoutFails);

        $withFails = Operator::validate('condition', 'abort');
        $this->assertStringContainsString('VALIDATE(condition)', $withFails);
        $this->assertStringContainsString('FAILS', $withFails);
        $this->assertStringContainsString('abort', $withFails);
    }

    /**
     * BrainCLI constants must match expected command format.
     */
    public function testBrainCLIConstants(): void
    {
        $this->assertSame('brain compile', BrainCLI::COMPILE);
        $this->assertSame('brain help', BrainCLI::HELP);
        $this->assertSame('brain docs', BrainCLI::DOCS);
        $this->assertSame('brain init', BrainCLI::INIT);
    }

    /**
     * BrainCLI static methods must append arguments.
     */
    public function testBrainCLIMethodsAppendArgs(): void
    {
        $this->assertSame('brain make:master Foo', BrainCLI::MAKE_MASTER('Foo'));
        $this->assertSame('brain make:command Bar', BrainCLI::MAKE_COMMAND('Bar'));
        $this->assertSame('brain docs --validate', BrainCLI::DOCS('--validate'));
    }

    /**
     * Operator::do() must chain with arrows.
     */
    public function testOperatorDoChaining(): void
    {
        $result = Operator::do('step1', 'step2', 'step3');

        // Must contain arrow separator between steps
        $this->assertStringContainsString('step1', $result);
        $this->assertStringContainsString('step2', $result);
        $this->assertStringContainsString('step3', $result);
    }

    /**
     * Operator output must be deterministic — same call = same output.
     */
    public function testOperatorDeterminism(): void
    {
        $first = Operator::if('cond', 'then', 'else');
        $second = Operator::if('cond', 'then', 'else');

        $this->assertSame($first, $second, 'Operator output must be deterministic');
    }
}
