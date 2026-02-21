<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Compilation\Tools\BashTool;
use BrainCore\Compilation\Tools\ReadTool;
use BrainCore\Compilation\Tools\EditTool;
use BrainCore\Compilation\Tools\WriteTool;
use BrainCore\Compilation\Tools\GlobTool;
use BrainCore\Compilation\Tools\GrepTool;
use BrainCore\Compilation\Tools\WebSearchTool;
use BrainCore\Compilation\Tools\WebFetchTool;
use BrainCore\Compilation\Tools\TaskTool;
use PHPUnit\Framework\TestCase;

/**
 * Tool output format invariants.
 *
 * Enterprise invariant: Tool::call() and Tool::describe() must produce
 * stable pseudo-syntax that LLMs parse. Format drift = broken agents.
 */
class ToolFormatTest extends TestCase
{
    /**
     * All tools must have a non-empty name.
     */
    public function testAllToolsHaveNames(): void
    {
        $tools = [
            BashTool::class,
            ReadTool::class,
            EditTool::class,
            WriteTool::class,
            GlobTool::class,
            GrepTool::class,
            WebSearchTool::class,
            WebFetchTool::class,
            TaskTool::class,
        ];

        foreach ($tools as $tool) {
            $name = $tool::name();
            $this->assertNotEmpty($name, "$tool must have a non-empty name");
            $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z]+$/', $name, "$tool name must be PascalCase");
        }
    }

    /**
     * Tool::call() must produce ToolName(args) format.
     */
    public function testCallProducesCorrectFormat(): void
    {
        $result = BashTool::call('ls -la');
        $this->assertStringStartsWith('Bash(', $result);
        $this->assertStringContainsString('ls -la', $result);

        $result = ReadTool::call('file.php');
        $this->assertStringStartsWith('Read(', $result);
        $this->assertStringContainsString('file.php', $result);

        $result = GlobTool::call('*.php');
        $this->assertStringStartsWith('Glob(', $result);
    }

    /**
     * Tool::call() with no args produces ToolName().
     */
    public function testCallWithNoArgs(): void
    {
        $result = BashTool::call();
        $this->assertSame('Bash()', $result);
    }

    /**
     * Tool::describe() produces multi-step format with END marker.
     */
    public function testDescribeProducesBlockFormat(): void
    {
        $result = BashTool::describe('git status', 'Check branch', 'Verify clean');

        $this->assertStringContainsString('Bash', $result);
        $this->assertStringContainsString('git status', $result);
        $this->assertStringContainsString('Check branch', $result);
        $this->assertStringContainsString('Verify clean', $result);
    }

    /**
     * Tool names must be unique across all tools.
     */
    public function testToolNamesAreUnique(): void
    {
        $names = [
            BashTool::name(),
            ReadTool::name(),
            EditTool::name(),
            WriteTool::name(),
            GlobTool::name(),
            GrepTool::name(),
            WebSearchTool::name(),
            WebFetchTool::name(),
            TaskTool::name(),
        ];

        $this->assertSame(
            count($names),
            count(array_unique($names)),
            'All tool names must be unique'
        );
    }

    /**
     * Tool::call() output is deterministic.
     */
    public function testCallDeterminism(): void
    {
        $first = BashTool::call('echo test');
        $second = BashTool::call('echo test');
        $this->assertSame($first, $second);

        $first = ReadTool::call('path/to/file');
        $second = ReadTool::call('path/to/file');
        $this->assertSame($first, $second);
    }

    /**
     * Tool::call() with multiple params separates with comma.
     */
    public function testCallMultipleParams(): void
    {
        $result = GrepTool::call('pattern', 'path');
        $this->assertStringContainsString('Grep(', $result);
        $this->assertStringContainsString(', ', $result);
    }

    /**
     * Expected tool name values match their class names.
     */
    public function testExpectedToolNames(): void
    {
        $this->assertSame('Bash', BashTool::name());
        $this->assertSame('Read', ReadTool::name());
        $this->assertSame('Edit', EditTool::name());
        $this->assertSame('Write', WriteTool::name());
        $this->assertSame('Glob', GlobTool::name());
        $this->assertSame('Grep', GrepTool::name());
        $this->assertSame('WebSearch', WebSearchTool::name());
        $this->assertSame('WebFetch', WebFetchTool::name());
        $this->assertSame('Task', TaskTool::name());
    }
}
