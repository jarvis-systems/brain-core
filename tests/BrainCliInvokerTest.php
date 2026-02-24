<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Contracts\BrainToolInvoker;
use BrainCore\Contracts\ProcessResult;
use BrainCore\Services\BrainCliInvoker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BrainCliInvokerTest extends TestCase
{
    public function test_implements_contract(): void
    {
        $invoker = new BrainCliInvoker();

        $this->assertInstanceOf(BrainToolInvoker::class, $invoker);
    }

    public function test_docs_search_parses_json_output(): void
    {
        $expectedJson = '{"schema_version":2,"total_matches":1,"files":[]}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($expectedJson, 'docs')
        );

        $result = $invoker->docsSearch('test query');

        $this->assertIsArray($result);
        $this->assertEquals(2, $result['schema_version']);
    }

    public function test_diagnose_parses_json_output(): void
    {
        $expectedJson = '{"self_dev_mode":true,"paths":{}}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($expectedJson, 'diagnose')
        );

        $result = $invoker->diagnose();

        $this->assertIsArray($result);
        $this->assertTrue($result['self_dev_mode']);
    }

    public function test_status_parses_json_output(): void
    {
        $expectedJson = '{"status":"ok","version":"v1.0.0"}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($expectedJson, 'status')
        );

        $result = $invoker->status();

        $this->assertIsArray($result);
        $this->assertEquals('ok', $result['status']);
    }

    public function test_list_includes_parses_json_output(): void
    {
        $expectedJson = '{"includes":[]}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($expectedJson, 'list:includes')
        );

        $result = $invoker->listIncludes('brain');

        $this->assertIsArray($result);
        $this->assertEquals([], $result['includes']);
    }

    public function test_list_masters_parses_json_output(): void
    {
        $expectedJson = '{"masters":[]}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($expectedJson, 'list:masters')
        );

        $result = $invoker->listMasters();

        $this->assertIsArray($result);
        $this->assertEquals([], $result['masters']);
    }

    public function test_readiness_check_parses_json_output(): void
    {
        $expectedJson = '{"ready":true,"checks":[]}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($expectedJson, 'readiness:check')
        );

        $result = $invoker->readinessCheck();

        $this->assertIsArray($result);
        $this->assertTrue($result['ready']);
    }

    public function test_throws_on_failed_command(): void
    {
        $invoker = new BrainCliInvoker(
            runner: $this->createMockFailedRunner('Command failed', 1)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Brain CLI failed');

        $invoker->diagnose();
    }

    public function test_throws_on_invalid_json(): void
    {
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner('not valid json')
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $invoker->diagnose();
    }

    public function test_throws_on_non_array_json(): void
    {
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner('"string not array"')
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-array JSON');

        $invoker->diagnose();
    }

    public function test_throws_on_secret_token_in_output(): void
    {
        $jsonWithSecret = '{"api_token":"sk-1234567890abcdefghijklmnopqrstuv"}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($jsonWithSecret)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret pattern');

        $invoker->diagnose();
    }

    public function test_throws_on_secret_key_in_output(): void
    {
        $jsonWithSecret = '{"api_key":"12345678901234567890abcdefghij"}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($jsonWithSecret)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret pattern');

        $invoker->diagnose();
    }

    public function test_throws_on_bearer_token_in_output(): void
    {
        $jsonWithSecret = '{"auth":"Bearer 12345678901234567890abcdefgh"}';
        $invoker = new BrainCliInvoker(
            runner: $this->createMockRunner($jsonWithSecret)
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret pattern');

        $invoker->diagnose();
    }

    public function test_docs_search_passes_limit_and_headers(): void
    {
        $runner = new class implements \BrainCore\Contracts\ProcessRunner {
            public string $lastCommand = '';

            public function run(string $command, ?string $cwd = null): ProcessResult
            {
                $this->lastCommand = $command;

                return new class implements ProcessResult {
                    public function successful(): bool { return true; }
                    public function output(): string { return '{"result":[]}'; }
                    public function errorOutput(): string { return ''; }
                    public function exitCode(): ?int { return 0; }
                };
            }
        };

        $invoker = new BrainCliInvoker(runner: $runner);
        $invoker->docsSearch('query', limit: 10, headers: 3);

        $this->assertStringContainsString('--limit=10', $runner->lastCommand);
        $this->assertStringContainsString('--headers=3', $runner->lastCommand);
    }

    public function test_working_directory_is_passed_to_runner(): void
    {
        $runner = new class implements \BrainCore\Contracts\ProcessRunner {
            public ?string $lastCwd = null;

            public function run(string $command, ?string $cwd = null): ProcessResult
            {
                $this->lastCwd = $cwd;

                return new class implements ProcessResult {
                    public function successful(): bool { return true; }
                    public function output(): string { return '{"ok":true}'; }
                    public function errorOutput(): string { return ''; }
                    public function exitCode(): ?int { return 0; }
                };
            }
        };

        $invoker = new BrainCliInvoker(runner: $runner, workingDirectory: '/tmp/test');
        $invoker->diagnose();

        $this->assertEquals('/tmp/test', $runner->lastCwd);
    }

    private function createMockRunner(string $output, string $expectedCmd = ''): \BrainCore\Contracts\ProcessRunner
    {
        $mock = $this->createMock(\BrainCore\Contracts\ProcessRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->with(
                $expectedCmd ? $this->stringContains($expectedCmd) : $this->anything(),
                $this->anything()
            )
            ->willReturn($this->createSuccessfulResult($output));

        return $mock;
    }

    private function createMockFailedRunner(string $error, int $exitCode): \BrainCore\Contracts\ProcessRunner
    {
        $mock = $this->createMock(\BrainCore\Contracts\ProcessRunner::class);
        $mock->expects($this->once())
            ->method('run')
            ->willReturn($this->createFailedResult($error, $exitCode));

        return $mock;
    }

    private function createSuccessfulResult(string $output): ProcessResult
    {
        return new class($output) implements ProcessResult {
            public function __construct(private readonly string $outputStr) {}

            public function successful(): bool { return true; }
            public function output(): string { return $this->outputStr; }
            public function errorOutput(): string { return ''; }
            public function exitCode(): ?int { return 0; }
        };
    }

    private function createFailedResult(string $error, int $exitCode): ProcessResult
    {
        return new class($error, $exitCode) implements ProcessResult {
            public function __construct(
                private readonly string $errorStr,
                private readonly int $exitCodeVal
            ) {}

            public function successful(): bool { return false; }
            public function output(): string { return ''; }
            public function errorOutput(): string { return $this->errorStr; }
            public function exitCode(): ?int { return $this->exitCodeVal; }
        };
    }
}
