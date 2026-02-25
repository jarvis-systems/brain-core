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
     * @param string|null $requestId Deterministic request hash
     * @param bool $redactionsApplied Whether any redactions were performed
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $serverId,
        public readonly string $tool,
        public readonly array $data = [],
        public readonly ?array $error = null,
        public readonly ?string $requestId = null,
        public readonly bool $redactionsApplied = false,
    ) {}

    public static function success(string $serverId, string $tool, array $data, ?string $requestId = null, bool $redactionsApplied = false): self
    {
        return new self(true, $serverId, $tool, $data, null, $requestId, $redactionsApplied);
    }

    public static function error(string $serverId, string $tool, string $code, string $reason, string $message, string $hint, ?string $requestId = null): self
    {
        return new self(false, $serverId, $tool, [], [
            'code' => $code,
            'reason' => $reason,
            'message' => $message,
            'hint' => $hint,
        ], $requestId);
    }

    /**
     * Convert to stable array for JSON output.
     */
    public function toStableArray(): array
    {
        $result = [
            'ok' => $this->ok,
            'server' => $this->serverId,
            'tool' => $this->tool,
        ];

        if ($this->requestId !== null) {
            $result['request_id'] = $this->requestId;
            $result['redactions_applied'] = $this->redactionsApplied;
        }

        if ($this->ok) {
            $result['data'] = $this->data;
        } else {
            $result['error'] = $this->error;
        }

        // Ensure stable key ordering
        ksort($result);

        return $result;
    }
}
