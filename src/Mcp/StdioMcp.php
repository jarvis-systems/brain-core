<?php

declare(strict_types=1);

namespace BrainCore\Mcp;

use BrainCore\Architectures\McpArchitecture;
use BrainCore\Enums\McpTypeEnum;

abstract class StdioMcp extends McpArchitecture
{
    /**
     * @param non-empty-string $command
     * @param array<int, non-empty-string> $args
     */
    public function __construct(
        protected McpTypeEnum $type,
        protected string $command,
        protected array $args,
    ) {
        parent::__construct();
    }

    /**
     * @return McpTypeEnum
     */
    protected static function defaultType(): McpTypeEnum
    {
        return McpTypeEnum::STDIO;
    }

    /**
     * @return non-empty-string
     */
    abstract public static function defaultCommand(): string;

    /**
     * @return array<int, string>
     */
    abstract public static function defaultArgs(): array;
}
