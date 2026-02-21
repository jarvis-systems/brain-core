<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Compilation\Runtime;
use PHPUnit\Framework\TestCase;

/**
 * Runtime compilation constants and path-building methods.
 *
 * Enterprise invariant: all constants must be template placeholders
 * ({{ NAME }}) that CLI resolves at compile time. Any change to
 * constant format breaks the entire compilation pipeline.
 */
class RuntimeTest extends TestCase
{
    /**
     * All constants must follow {{ NAME }} template pattern.
     */
    public function testConstantsAreTemplatePlaceholders(): void
    {
        $constants = [
            'PROJECT_DIRECTORY' => Runtime::PROJECT_DIRECTORY,
            'BRAIN_DIRECTORY' => Runtime::BRAIN_DIRECTORY,
            'NODE_DIRECTORY' => Runtime::NODE_DIRECTORY,
            'TIMESTAMP' => Runtime::TIMESTAMP,
            'DATE' => Runtime::DATE,
            'TIME' => Runtime::TIME,
            'YEAR' => Runtime::YEAR,
            'MONTH' => Runtime::MONTH,
            'DAY' => Runtime::DAY,
            'UNIQUE_ID' => Runtime::UNIQUE_ID,
            'AGENT' => Runtime::AGENT,
            'BRAIN_FILE' => Runtime::BRAIN_FILE,
            'MCP_FILE' => Runtime::MCP_FILE,
            'BRAIN_FOLDER' => Runtime::BRAIN_FOLDER,
            'AGENTS_FOLDER' => Runtime::AGENTS_FOLDER,
            'COMMANDS_FOLDER' => Runtime::COMMANDS_FOLDER,
            'SKILLS_FOLDER' => Runtime::SKILLS_FOLDER,
        ];

        foreach ($constants as $name => $value) {
            $this->assertMatchesRegularExpression(
                '/^\{\{ [A-Z_]+ \}\}$/',
                $value,
                "Runtime::$name must be {{ $name }} template placeholder"
            );
        }
    }

    /**
     * Path methods must append segments to base constant.
     */
    public function testPathMethodsAppendSegments(): void
    {
        $this->assertSame(
            '{{ PROJECT_DIRECTORY }}src/file.php',
            Runtime::PROJECT_DIRECTORY('src/file.php')
        );

        $this->assertSame(
            '{{ NODE_DIRECTORY }}Brain.php',
            Runtime::NODE_DIRECTORY('Brain.php')
        );

        $this->assertSame(
            '{{ BRAIN_DIRECTORY }}config',
            Runtime::BRAIN_DIRECTORY('config')
        );
    }

    /**
     * Path methods with multiple segments join with /.
     */
    public function testPathMethodsJoinMultipleSegments(): void
    {
        $this->assertSame(
            '{{ AGENTS_FOLDER }}explore/config.md',
            Runtime::AGENTS_FOLDER('explore', 'config.md')
        );
    }

    /**
     * Path methods with no args return base constant.
     */
    public function testPathMethodsReturnBaseWithoutArgs(): void
    {
        $this->assertSame('{{ PROJECT_DIRECTORY }}', Runtime::PROJECT_DIRECTORY());
        $this->assertSame('{{ NODE_DIRECTORY }}', Runtime::NODE_DIRECTORY());
        $this->assertSame('{{ BRAIN_FOLDER }}', Runtime::BRAIN_FOLDER());
        $this->assertSame('{{ AGENTS_FOLDER }}', Runtime::AGENTS_FOLDER());
        $this->assertSame('{{ COMMANDS_FOLDER }}', Runtime::COMMANDS_FOLDER());
        $this->assertSame('{{ SKILLS_FOLDER }}', Runtime::SKILLS_FOLDER());
    }

    /**
     * print() must generate {{ NAME }} from any string.
     */
    public function testPrintGeneratesTemplate(): void
    {
        $this->assertSame('{{ CUSTOM_VAR }}', Runtime::print('custom_var'));
        $this->assertSame('{{ MY_PATH }}', Runtime::print('my_path'));
    }

    /**
     * __callStatic handles dynamic constant access.
     */
    public function testCallStaticReturnsTemplateForUnknown(): void
    {
        // Unknown constants fall through to print()
        $this->assertSame('{{ UNKNOWN_CONST }}', Runtime::UNKNOWN_CONST());
    }

    /**
     * Constants must be deterministic — same value on every access.
     */
    public function testConstantDeterminism(): void
    {
        $first = Runtime::PROJECT_DIRECTORY;
        $second = Runtime::PROJECT_DIRECTORY;
        $this->assertSame($first, $second);

        $first = Runtime::NODE_DIRECTORY('Brain.php');
        $second = Runtime::NODE_DIRECTORY('Brain.php');
        $this->assertSame($first, $second);
    }
}
