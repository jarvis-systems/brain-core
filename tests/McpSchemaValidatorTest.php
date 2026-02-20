<?php

declare(strict_types=1);

namespace BrainCore\Tests;

use BrainCore\Mcp\McpSchemaValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class McpSchemaValidatorTest extends TestCase
{
    private static function schema(): array
    {
        return [
            'task_update' => [
                'required' => ['task_id'],
                'allowed' => ['task_id', 'status', 'comment'],
                'types' => [
                    'task_id' => 'integer',
                    'status' => 'string',
                    'comment' => 'string',
                ],
            ],
            'store_memory' => [
                'required' => ['content'],
                'allowed' => ['content', 'category', 'tags'],
                'types' => [
                    'content' => 'string',
                    'category' => 'string',
                    'tags' => 'array',
                ],
            ],
        ];
    }

    public function testMissingRequiredKeyThrowsInStandardMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing required key 'task_id'");

        McpSchemaValidator::validate(
            'task_update',
            ['status' => 'completed'],
            self::schema(),
            'standard'
        );
    }

    public function testUnknownKeyThrowsInStrictMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown keys [hallucinated_param]');

        McpSchemaValidator::validate(
            'task_update',
            ['task_id' => 1, 'hallucinated_param' => 'oops'],
            self::schema(),
            'strict'
        );
    }

    public function testUnknownKeyPassesInStandardMode(): void
    {
        // Standard mode only checks required keys, extra keys are allowed
        McpSchemaValidator::validate(
            'task_update',
            ['task_id' => 1, 'hallucinated_param' => 'passes silently'],
            self::schema(),
            'standard'
        );

        $this->assertTrue(true, 'No exception thrown — standard mode allows extra keys');
    }

    public function testTypeMismatchThrowsInParanoidMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("key 'task_id' must be integer");

        McpSchemaValidator::validate(
            'task_update',
            ['task_id' => 'not-an-integer', 'status' => 'completed'],
            self::schema(),
            'paranoid'
        );
    }

    public function testUnknownMethodPassesInStandardMode(): void
    {
        McpSchemaValidator::validate(
            'nonexistent_method',
            ['anything' => 'goes'],
            self::schema(),
            'standard'
        );

        $this->assertTrue(true, 'Unknown methods pass in standard mode');
    }

    public function testUnknownMethodThrowsInStrictMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("unknown method 'nonexistent_method' in strict mode");

        McpSchemaValidator::validate(
            'nonexistent_method',
            ['anything' => 'goes'],
            self::schema(),
            'strict'
        );
    }

    public function testUnknownMethodThrowsInParanoidMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("unknown method 'nonexistent_method' in paranoid mode");

        McpSchemaValidator::validate(
            'nonexistent_method',
            ['anything' => 'goes'],
            self::schema(),
            'paranoid'
        );
    }

    public function testUnknownMethodPassesWithEmptySchema(): void
    {
        // Empty schema = no schema trait (e.g., Context7Mcp). Always passes.
        McpSchemaValidator::validate(
            'any_method',
            ['anything' => 'goes'],
            [],
            'paranoid'
        );

        $this->assertTrue(true, 'Empty schema passes even in paranoid mode');
    }

    public function testArrayTypeValidationInParanoidMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("key 'tags' must be array");

        McpSchemaValidator::validate(
            'store_memory',
            ['content' => 'test', 'tags' => 'not-an-array'],
            self::schema(),
            'paranoid'
        );
    }

    public function testValidCallPassesAllModes(): void
    {
        $args = ['task_id' => 42, 'status' => 'completed'];

        foreach (['standard', 'strict', 'paranoid'] as $mode) {
            McpSchemaValidator::validate('task_update', $args, self::schema(), $mode);
        }

        $this->assertTrue(true, 'Valid call passes all validation modes');
    }
}
