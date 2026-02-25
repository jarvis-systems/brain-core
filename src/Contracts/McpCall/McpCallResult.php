<?php

declare(strict_types=1);

namespace BrainCore\Contracts\McpCall;

/**
 * MCP Call Result DTO
 */
final class McpCallResult
{
    /**
     * @param bool $ok        Whether the call was successful
     * @param string $serverId The ID of the server called
     * @param string $tool     The name of the tool called
     * @param array $data      Response data (redacted)
     * @param array|null $error Error details if ok is false
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $serverId,
        public readonly string $tool,
        public readonly array $data = [],
        public readonly ?array $error = null,
    ) {}

    public static function success(string $serverId, string $tool, array $data): self
    {
        return new self(true, $serverId, $tool, $data);
    }

    public static function error(string $serverId, string $tool, string $code, string $reason, string $message, string $hint): self
    {
        return new self(false, $serverId, $tool, [], [
            'code' => $code,
            'reason' => $reason,
            'message' => $message,
            'hint' => $hint,
        ]);
    }

    /**
     * Convert to stable array for JSON output.
     */
    public function toStableArray(): array
    {
        if ($this->ok) {
            return [
                'ok' => true,
                'server' => $this->serverId,
                'tool' => $this->tool,
                'data' => $this->data,
            ];
        }

        return [
            'ok' => false,
            'server' => $this->serverId,
            'tool' => $this->tool,
            'error' => $this->error,
        ];
    }
}
